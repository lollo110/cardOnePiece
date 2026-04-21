<?php

namespace App\Controller;

use App\Entity\BlogComment;
use App\Entity\BlogTopic;
use App\Entity\Card;
use App\Entity\CardComment;
use App\Entity\User;
use App\Repository\BlogCommentRepository;
use App\Repository\BlogTopicRepository;
use App\Repository\CardCommentRepository;
use App\Service\CardService;
use App\Service\CommentModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CardController extends AbstractController
{
    private const BLOG_TOPICS = [
        [
            'title' => 'Trading & collection',
            'slug' => 'trading',
            'description' => 'Talk about trades, favorite cards, binder goals, new pickups and collection plans.',
            'button' => 'Open trading room',
        ],
        [
            'title' => 'Game',
            'slug' => 'game',
            'description' => 'Talk about decks, matchups, rules, tournaments and the current format.',
            'button' => 'Open game room',
        ],
    ];

    #[Route('/', name: 'app_home')]
    public function home(CardService $service, BlogTopicRepository $topicRepository, EntityManagerInterface $entityManager): Response
    {
        return $this->render('home/index.html.twig', [
            'recentCards' => $service->recentCards(8),
            'topics' => $this->syncBlogTopics($topicRepository, $entityManager),
        ]);
    }

    #[Route('/market', name: 'market')]
    public function market(): Response
    {
        return $this->redirectToRoute('blog');
    }

    #[Route('/blog', name: 'blog')]
    public function blog(BlogTopicRepository $topicRepository, EntityManagerInterface $entityManager): Response
    {
        return $this->render('blog/index.html.twig', [
            'topics' => $this->syncBlogTopics($topicRepository, $entityManager),
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_topic', requirements: ['slug' => 'trading|game|trading-prices'], methods: ['GET', 'POST'])]
    public function blogTopic(
        string $slug,
        Request $request,
        BlogTopicRepository $topicRepository,
        BlogCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
    ): Response {
        if ($slug === 'trading-prices') {
            return $this->redirectToRoute('blog_topic', ['slug' => 'trading'], Response::HTTP_MOVED_PERMANENTLY);
        }

        $topics = $this->syncBlogTopics($topicRepository, $entityManager);
        $topic = $topicRepository->findOneBy(['slug' => $slug]);

        if (!$topic) {
            throw $this->createNotFoundException('Blog topic not found.');
        }

        if ($request->isMethod('POST')) {
            $this->handleBlogCommentSubmission($request, $topic, $entityManager, $validator, $commentModerationService);

            return $this->redirect($this->generateUrl('blog_topic', ['slug' => $slug]) . '#discussion');
        }

        return $this->render('blog/topic.html.twig', [
            'topic' => $topic,
            'topics' => $topics,
            'comments' => $commentRepository->findForTopic($topic),
            'commentAuthorName' => $this->resolveCommentAuthorName($request),
        ]);
    }

    #[Route('/cards', name: 'cards')]
    public function index(Request $request, CardService $service): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $sort = (string) $request->query->get('sort', 'relevance');
        $rarity = trim((string) $request->query->get('rarity', ''));
        $response = $service->searchCards($query !== '' ? $query : null, $page, $sort, $rarity !== '' ? $rarity : null);
        $paging = $response['paging'] ?? ['current' => 1, 'total' => 1, 'per_page' => 20];
        $results = $response['data'] ?? [];
        $rarityOptions = $service->rarityOptions($query !== '' ? $query : null);

        return $this->render('card/index.html.twig', [
            'results' => $results,
            'query' => $query,
            'sort' => $sort,
            'rarity' => $rarity,
            'paging' => [
                'current' => (int) ($paging['current'] ?? 1),
                'total' => (int) ($paging['total'] ?? 1),
                'per_page' => (int) ($paging['per_page'] ?? 20),
            ],
            'totalResults' => (int) ($response['results'] ?? 0),
            'error' => $response['error'] ?? null,
            'rarityOptions' => $rarityOptions,
        ]);
    }

    #[Route('/cards/filter', name: 'cards_filter', methods: ['GET'])]
    public function filter(Request $request, CardService $service): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'relevance');
        $rarity = trim((string) $request->query->get('rarity', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $response = $service->searchCards($query !== '' ? $query : null, $page, $sort, $rarity !== '' ? $rarity : null);
        $paging = $response['paging'] ?? ['current' => 1, 'total' => 1, 'per_page' => 20];

        return $this->json([
            'html' => $this->renderView('card/_results.html.twig', [
                'results' => $response['data'] ?? [],
                'query' => $query,
                'sort' => $sort,
                'rarity' => $rarity,
                'paging' => [
                    'current' => (int) ($paging['current'] ?? 1),
                    'total' => (int) ($paging['total'] ?? 1),
                    'per_page' => (int) ($paging['per_page'] ?? 20),
                ],
                'totalResults' => (int) ($response['results'] ?? 0),
                'error' => $response['error'] ?? null,
                'rarityOptions' => $service->rarityOptions($query !== '' ? $query : null),
            ]),
        ]);
    }

    #[Route('/cards/suggestions', name: 'cards_suggestions')]
    public function suggestions(Request $request, CardService $service): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        return $this->json($service->suggestCards($query));
    }

    #[Route('/cards/{id}', name: 'card_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        Request $request,
        CardService $service,
        CardCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $card = $service->findCard($id, $request->query->get('name'));

        if (!$card) {
            throw $this->createNotFoundException('Card not found.');
        }

        $cardEntity = $entityManager->getRepository(\App\Entity\Card::class)->findOneBy(['apiId' => $id]);
        $commentLanguages = $cardEntity
            ? $this->cardCommentLanguagesFromEntity($cardEntity)
            : $this->cardCommentLanguages($card);
        $defaultLanguage = $commentLanguages[0]['key'] ?? 'english';

        return $this->render('card/show.html.twig', [
            'card' => $card,
            'cardCommentOptions' => [
                'discussionTypes' => $this->cardDiscussionTypes(),
                'languages' => $commentLanguages,
                'defaultDiscussionType' => CardComment::DISCUSSION_TRADING,
                'defaultLanguage' => $defaultLanguage,
            ],
            'initialComments' => $cardEntity ? $this->serializeCardComments(
                $commentRepository->findForCardDiscussion($cardEntity, CardComment::DISCUSSION_TRADING, $defaultLanguage)
            ) : [],
            'commentAuthorName' => $this->resolveCommentAuthorName($request),
        ]);
    }

    #[Route('/cards/{id}/comments', name: 'card_comments', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function cardComments(
        int $id,
        Request $request,
        CardCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
    ): JsonResponse {
        $cardEntity = $entityManager->getRepository(Card::class)->findOneBy(['apiId' => $id]);

        if (!$cardEntity) {
            return $this->json(['error' => 'Card not found.'], Response::HTTP_NOT_FOUND);
        }

        $languageMap = $this->cardCommentLanguageMapFromEntity($cardEntity);
        $discussionTypes = $this->cardDiscussionTypes();

        if ($request->isMethod('POST')) {
            $discussionType = (string) $request->request->get('discussion_type', CardComment::DISCUSSION_TRADING);
            $language = (string) $request->request->get('language', array_key_first($languageMap) ?: 'english');

            if (!isset($discussionTypes[$discussionType])) {
                return $this->json(['error' => 'Invalid discussion type.'], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($languageMap[$language])) {
                return $this->json(['error' => 'Invalid language.'], Response::HTTP_BAD_REQUEST);
            }

            if (!$this->isCsrfTokenValid('card_comment_' . $id, (string) $request->request->get('_token'))) {
                return $this->json(['error' => 'Unable to post your message right now.'], Response::HTTP_FORBIDDEN);
            }

            $content = trim((string) $request->request->get('content', ''));

            if ($content === '') {
                return $this->json(['error' => 'Write a message before posting.'], Response::HTTP_BAD_REQUEST);
            }

            $comment = (new CardComment())
                ->setCard($cardEntity)
                ->setAuthorUser($this->currentUser())
                ->setAuthorName($this->resolveCommentAuthorName($request))
                ->setDiscussionType($discussionType)
                ->setLanguage($language)
                ->setContent($content);

            $validationError = $this->firstValidationError($validator->validate($comment));

            if ($validationError !== null) {
                return $this->json(['error' => $validationError], Response::HTTP_BAD_REQUEST);
            }

            $moderationResult = $commentModerationService->moderate($comment->getContent());

            if (!$moderationResult->isApproved()) {
                $comment
                    ->setModerationStatus(CardComment::STATUS_BLOCKED)
                    ->setModerationReason($moderationResult->getReason());
            }

            $entityManager->persist($comment);
            $entityManager->flush();

            $payload = [
                'discussionType' => $discussionType,
                'discussionLabel' => $discussionTypes[$discussionType] ?? 'Trading',
                'language' => $language,
                'languageLabel' => $languageMap[$language] ?? ucfirst($language),
                'comments' => $this->serializeCardComments(
                    $commentRepository->findForCardDiscussion($cardEntity, $discussionType, $language)
                ),
            ];

            if (!$moderationResult->isApproved()) {
                $payload['notice'] = 'Your comment is hidden for review because it may contain inappropriate language.';
            }

            return $this->json($payload, $moderationResult->isApproved() ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
        }

        $discussionType = (string) $request->query->get('discussion', CardComment::DISCUSSION_TRADING);
        $language = (string) $request->query->get('language', array_key_first($languageMap) ?: 'english');

        if (!isset($discussionTypes[$discussionType])) {
            $discussionType = CardComment::DISCUSSION_TRADING;
        }

        if (!isset($languageMap[$language])) {
            $language = array_key_first($languageMap) ?: 'english';
        }

        return $this->json([
            'discussionType' => $discussionType,
            'discussionLabel' => $discussionTypes[$discussionType] ?? 'Trading',
            'language' => $language,
            'languageLabel' => $languageMap[$language] ?? ucfirst($language),
            'comments' => $this->serializeCardComments(
                $commentRepository->findForCardDiscussion($cardEntity, $discussionType, $language)
            ),
        ]);
    }

    private function syncBlogTopics(BlogTopicRepository $topicRepository, EntityManagerInterface $entityManager): array
    {
        $stored = [];

        foreach ($topicRepository->findOrdered() as $topic) {
            $stored[$topic->getSlug()] = $topic;
        }

        foreach (self::BLOG_TOPICS as $definition) {
            $topic = $stored[$definition['slug']]
                ?? ($definition['slug'] === 'trading' ? ($stored['trading-prices'] ?? null) : null)
                ?? new BlogTopic();
            $topic
                ->setTitle($definition['title'])
                ->setSlug($definition['slug'])
                ->setDescription($definition['description']);
            $entityManager->persist($topic);
            $stored[$definition['slug']] = $topic;
        }

        $entityManager->flush();

        return array_values(array_map(
            fn (array $definition): BlogTopic => $stored[$definition['slug']],
            self::BLOG_TOPICS
        ));
    }

    private function handleBlogCommentSubmission(
        Request $request,
        BlogTopic $topic,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
    ): void
    {
        if (!$this->isCsrfTokenValid('blog_comment_' . $topic->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Unable to post your message right now.');

            return;
        }

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '') {
            $this->addFlash('error', 'Write a message before posting.');

            return;
        }

        $comment = (new BlogComment())
            ->setTopic($topic)
            ->setAuthorUser($this->currentUser())
            ->setAuthorName($this->resolveCommentAuthorName($request))
            ->setContent($content);

        $validationError = $this->firstValidationError($validator->validate($comment));

        if ($validationError !== null) {
            $this->addFlash('error', $validationError);

            return;
        }

        $moderationResult = $commentModerationService->moderate($comment->getContent());

        if (!$moderationResult->isApproved()) {
            $comment
                ->setModerationStatus(BlogComment::STATUS_BLOCKED)
                ->setModerationReason($moderationResult->getReason());
        }

        $entityManager->persist($comment);
        $entityManager->flush();

        if ($moderationResult->isApproved()) {
            $this->addFlash('success', 'Your message is live.');

            return;
        }

        $this->addFlash('warning', 'Your message is hidden for review because it may contain inappropriate language.');
    }

    private function resolveCommentAuthorName(Request $request): string
    {
        return $this->currentUser()?->getUsername() ?? $this->guestCommentAlias($request);
    }

    private function cardDiscussionTypes(): array
    {
        return [
            CardComment::DISCUSSION_TRADING => 'Trading',
            CardComment::DISCUSSION_GAME => 'Game',
        ];
    }

    private function cardCommentLanguages(array $card): array
    {
        $labels = $this->normalizeLanguageLabels($card['languages'] ?? []);

        if ($labels === []) {
            $labels = ['English'];
        }

        return $this->languageOptionsFromLabels($labels);
    }

    private function cardCommentLanguagesFromEntity(Card $card): array
    {
        $map = $this->cardCommentLanguageMapFromEntity($card);
        $options = [];

        foreach ($map as $key => $label) {
            $options[] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        if ($options === []) {
            return [['key' => 'english', 'label' => 'English']];
        }

        return $options;
    }

    private function cardCommentLanguageMapFromEntity(Card $card): array
    {
        $labels = $this->normalizeLanguageLabels(($card->getRawData() ?? [])['languages'] ?? []);

        if ($labels === []) {
            $labels = ['English'];
        }

        $map = [];

        foreach ($labels as $label) {
            $map[$this->normalizeCardCommentLanguage($label)] = $label;
        }

        return $map;
    }

    /**
     * @param array<int, mixed> $languages
     * @return list<string>
     */
    private function normalizeLanguageLabels(array $languages): array
    {
        $labels = array_values(array_filter(
            array_map(static fn ($language): string => trim((string) $language), $languages),
            static fn (string $language): bool => $language !== ''
        ));

        return array_values(array_unique($labels));
    }

    /**
     * @param list<string> $labels
     * @return list<array{key: string, label: string}>
     */
    private function languageOptionsFromLabels(array $labels): array
    {
        return array_map(
            fn (string $label): array => [
                'key' => $this->normalizeCardCommentLanguage($label),
                'label' => $label,
            ],
            $labels
        );
    }

    private function normalizeCardCommentLanguage(string $label): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($label))) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'english';
    }

    private function serializeCardComments(array $comments): array
    {
        return array_map(static fn (CardComment $comment): array => [
            'authorName' => $comment->getAuthorName(),
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format('Y-m-d H:i'),
            'discussionType' => $comment->getDiscussionType(),
            'language' => $comment->getLanguage(),
        ], $comments);
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function guestCommentAlias(Request $request): string
    {
        $session = $request->getSession();
        $alias = (string) $session->get('guest_comment_alias', '');

        if ($alias !== '') {
            return $alias;
        }

        $alias = 'Guest ' . random_int(1000, 999999);
        $session->set('guest_comment_alias', $alias);

        return $alias;
    }

    /**
     * @param iterable<ConstraintViolationInterface> $violations
     */
    private function firstValidationError(iterable $violations): ?string
    {
        foreach ($violations as $violation) {
            return $violation->getMessage();
        }

        return null;
    }
}
