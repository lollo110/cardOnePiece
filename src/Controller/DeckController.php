<?php

namespace App\Controller;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\User;
use App\Repository\CardRepository;
use App\Repository\DeckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeckController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/decks', name: 'decks')]
    public function index(DeckRepository $deckRepository): Response
    {
        $user = $this->currentUser();

        return $this->render('deck/index.html.twig', [
            'publicDecks' => $deckRepository->findPublicDecks(),
            'myDecks' => $user ? $deckRepository->findForOwner($user) : [],
        ]);
    }

    #[Route('/decks/new', name: 'deck_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CardRepository $cardRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): Response {
        $user = $this->currentUser();

        if (!$user) {
            $this->addFlash('error', $this->translator->trans('deck.notice.login_required'));

            return $this->redirectToRoute('app_login');
        }

        $form = [
            'title' => trim((string) $request->request->get('title', '')),
            'archetype' => trim((string) $request->request->get('archetype', '')),
            'description' => trim((string) $request->request->get('description', '')),
            'visibility' => (string) $request->request->get('visibility', 'public'),
            'decklist' => trim((string) $request->request->get('decklist', '')),
            'cards' => $this->submittedDeckCards($request),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('deck_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', $this->translator->trans('comments.error.csrf'));

                return $this->renderDeckForm($form, $cardRepository);
            }

            $deck = (new Deck())
                ->setOwner($user)
                ->setTitle($form['title'])
                ->setSlug($this->slugify($form['title']))
                ->setArchetype($form['archetype'])
                ->setDescription($form['description'])
                ->setIsPublic($form['visibility'] !== 'private')
                ->setUpdatedAt(new \DateTimeImmutable());

            $unmatched = $form['cards'] !== []
                ? $this->fillDeckFromSubmittedCards($deck, $form['cards'], $cardRepository)
                : $this->fillDeckFromList($deck, $form['decklist'], $cardRepository);
            $validationError = $this->firstValidationError($validator->validate($deck));

            if ($validationError !== null) {
                $this->addFlash('error', $validationError);

                return $this->renderDeckForm($form, $cardRepository);
            }

            if ($deck->getCards()->isEmpty()) {
                $this->addFlash('error', $this->translator->trans('deck.notice.no_cards'));

                return $this->renderDeckForm($form, $cardRepository);
            }

            $entityManager->persist($deck);
            $entityManager->flush();

            if ($unmatched !== []) {
                $this->addFlash('warning', $this->translator->trans('deck.notice.unmatched', [
                    '%lines%' => implode(', ', array_slice($unmatched, 0, 6)),
                ]));
            }

            $this->addFlash('success', $this->translator->trans('deck.notice.created'));

            return $this->redirectToRoute('deck_show', ['id' => $deck->getId()]);
        }

        return $this->renderDeckForm($form, $cardRepository);
    }

    #[Route('/decks/cards/search', name: 'deck_card_search', methods: ['GET'])]
    public function searchCards(Request $request, CardRepository $cardRepository): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $collection = trim((string) $request->query->get('collection', ''));

        return $this->json(array_map(
            static fn (\App\Entity\Card $card): array => [
                'id' => $card->getId(),
                'apiId' => $card->getApiId(),
                'name' => $card->getNameNumbered() ?: $card->getName(),
                'cardNumber' => $card->getCardNumber(),
                'rarity' => $card->getRarity(),
                'image' => $card->getImage(),
                'set' => $card->getEpisode()?->getName(),
            ],
            $cardRepository->findDeckBuilderMatches($query, 12, $collection !== '' ? $collection : null)
        ));
    }

    #[Route('/decks/{id}', name: 'deck_show', requirements: ['id' => '\d+'])]
    public function show(int $id, DeckRepository $deckRepository): Response
    {
        $deck = $deckRepository->findVisible($id, $this->currentUser());

        if (!$deck) {
            throw $this->createNotFoundException('Deck not found.');
        }

        return $this->render('deck/show.html.twig', [
            'deck' => $deck,
            'sections' => $this->groupDeckCards($deck),
        ]);
    }

    /**
     * @return list<string>
     */
    private function fillDeckFromList(Deck $deck, string $decklist, CardRepository $cardRepository): array
    {
        $section = 'main';
        $position = 0;
        $unmatched = [];

        foreach (preg_split('/\R/', $decklist) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }

            if (preg_match('/^(leader|main|character|event|stage|don|side|extra)\s*:$/i', $line, $matches) === 1) {
                $section = $this->normalizeSection($matches[1]);

                continue;
            }

            $quantity = 1;
            $cardText = $line;

            if (preg_match('/^(\d{1,2})\s*x?\s+(.+)$/i', $line, $matches) === 1) {
                $quantity = (int) $matches[1];
                $cardText = trim($matches[2]);
            }

            $card = $cardRepository->findBestMatchForDeckLine($cardText);

            if (!$card) {
                $unmatched[] = $line;

                continue;
            }

            $deck->addCard(
                (new DeckCard())
                    ->setCard($card)
                    ->setQuantity($quantity)
                    ->setSection($section)
                    ->setPosition($position++)
            );
        }

        return $unmatched;
    }

    /**
     * @param list<array<string, mixed>> $submittedCards
     * @return list<string>
     */
    private function fillDeckFromSubmittedCards(Deck $deck, array $submittedCards, CardRepository $cardRepository): array
    {
        $position = 0;
        $unmatched = [];

        foreach ($submittedCards as $submittedCard) {
            $cardId = (int) ($submittedCard['card_id'] ?? 0);
            $quantity = (int) ($submittedCard['quantity'] ?? 1);
            $section = $this->normalizeSection((string) ($submittedCard['section'] ?? 'main'));

            if ($cardId <= 0) {
                continue;
            }

            $card = $cardRepository->find($cardId);

            if (!$card) {
                $unmatched[] = (string) $cardId;

                continue;
            }

            $deck->addCard(
                (new DeckCard())
                    ->setCard($card)
                    ->setQuantity($quantity)
                    ->setSection($section)
                    ->setPosition($position++)
            );
        }

        return $unmatched;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function submittedDeckCards(Request $request): array
    {
        $cards = $request->request->all('deck_cards');

        if (!is_array($cards)) {
            return [];
        }

        return array_values(array_filter($cards, static fn (mixed $card): bool => is_array($card)));
    }

    /**
     * @return array<string, list<DeckCard>>
     */
    private function groupDeckCards(Deck $deck): array
    {
        $sections = [];

        foreach ($deck->getCards() as $deckCard) {
            $sections[$deckCard->getSection()][] = $deckCard;
        }

        return $sections;
    }

    private function normalizeSection(string $section): string
    {
        $section = mb_strtolower(trim($section));

        return match ($section) {
            'leader' => 'leader',
            'don' => 'don',
            'side', 'extra' => 'side',
            default => 'main',
        };
    }

    private function slugify(string $value): string
    {
        $slug = mb_strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'deck';
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function renderDeckForm(array $form, CardRepository $cardRepository): Response
    {
        return $this->render('deck/new.html.twig', [
            'form' => $form,
            'collectionOptions' => $cardRepository->findCollectionOptions(),
        ]);
    }

    private function firstValidationError(iterable $violations): ?string
    {
        foreach ($violations as $violation) {
            if ($violation instanceof ConstraintViolationInterface) {
                return $violation->getMessage();
            }
        }

        return null;
    }
}
