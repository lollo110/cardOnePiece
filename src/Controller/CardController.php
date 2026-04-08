<?php

namespace App\Controller;

use App\Service\CardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(CardService $service): Response
    {
        $response = $service->trendingCards();
        $marketMovers = $service->marketMovers(4, 3);

        return $this->render('home/index.html.twig', [
            'trendingCards' => array_slice($response['data'] ?? [], 0, 8),
            'risingCards' => $marketMovers['rising'],
            'fallingCards' => $marketMovers['falling'],
            'error' => $response['error'] ?? null,
        ]);
    }

    #[Route('/market', name: 'market')]
    public function market(CardService $service): Response
    {
        return $this->render('market/index.html.twig', [
            'marketMovers' => $service->marketMovers(12, 5),
        ]);
    }

    #[Route('/blog', name: 'blog')]
    public function blog(): Response
    {
        return $this->render('blog/index.html.twig', [
            'topics' => [
                [
                    'title' => 'Trading & prices',
                    'slug' => 'trading-prices',
                    'description' => 'Talk about trades, card values, market moves, reprints and collection goals.',
                    'button' => 'Open trading blog',
                ],
                [
                    'title' => 'Game',
                    'slug' => 'game',
                    'description' => 'Talk about decks, matchups, rules, tournaments and the current format.',
                    'button' => 'Open game blog',
                ],
            ],
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_topic', requirements: ['slug' => 'trading-prices|game'])]
    public function blogTopic(string $slug): Response
    {
        $topics = [
            'trading-prices' => [
                'title' => 'Trading & prices',
                'slug' => 'trading-prices',
                'description' => 'Talk about trades, card values, market moves, reprints and collection goals.',
            ],
            'game' => [
                'title' => 'Game',
                'slug' => 'game',
                'description' => 'Talk about decks, matchups, rules, tournaments and the current format.',
            ],
        ];

        return $this->render('blog/topic.html.twig', [
            'topic' => $topics[$slug],
        ]);
    }

    #[Route('/cards', name: 'cards')]
    public function index(Request $request, CardService $service): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $sort = (string) $request->query->get('sort', 'relevance');
        $response = $service->searchCards($query !== '' ? $query : null, $page, $sort);
        $paging = $response['paging'] ?? ['current' => 1, 'total' => 1, 'per_page' => 20];

        return $this->render('card/index.html.twig', [
            'results' => $response['data'] ?? [],
            'query' => $query,
            'sort' => $sort,
            'paging' => [
                'current' => (int) ($paging['current'] ?? 1),
                'total' => (int) ($paging['total'] ?? 1),
                'per_page' => (int) ($paging['per_page'] ?? 20),
            ],
            'totalResults' => (int) ($response['results'] ?? 0),
            'error' => $response['error'] ?? null,
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

    #[Route('/cards/{id}/prices', name: 'card_prices', requirements: ['id' => '\d+'])]
    public function prices(int $id, Request $request, CardService $service): JsonResponse
    {
        $card = $service->findCard($id, $request->query->get('name'));

        if (!$card) {
            return $this->json(['error' => 'Card not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($service->priceChartForLanguage($card, (string) $request->query->get('language', 'all')));
    }

    #[Route('/cards/{id}', name: 'card_show', requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request, CardService $service): Response
    {
        $card = $service->findCard($id, $request->query->get('name'));

        if (!$card) {
            throw $this->createNotFoundException('Card not found.');
        }

        return $this->render('card/show.html.twig', [
            'card' => $card,
            'languageOptions' => $service->languageOptions(),
            'initialPriceChart' => $service->priceChartForLanguage($card, 'all'),
        ]);
    }
}
