<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\CardPrice;
use App\Entity\CardPriceHistory;
use App\Repository\CardPriceHistoryRepository;
use App\Repository\CardPriceRepository;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CardService
{
    private const API_URL = 'https://cardmarket-api-tcg.p.rapidapi.com/one-piece/cards/search';
    private const API_HEADERS = [
        'X-RapidAPI-Key' => 'a6ce6a4e75mshca2679e1b271a5dp144f81jsnb4780953c5c4',
        'X-RapidAPI-Host' => 'cardmarket-api-tcg.p.rapidapi.com',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CardRepository $cardRepository,
        private readonly CardPriceRepository $cardPriceRepository,
        private readonly CardPriceHistoryRepository $cardPriceHistoryRepository,
        private readonly CardTraderPriceService $cardTraderPriceService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function searchCards(?string $query = null, int $page = 1, string $sort = 'relevance'): array
    {
        $query = trim((string) $query);
        $page = max(1, $page);
        $perPage = 20;
        $sort = $this->normalizeSort($sort);
        $totalResults = $this->cardRepository->countSearch($query !== '' ? $query : null);
        $cards = $this->cardRepository->searchPage($query !== '' ? $query : null, $page, $perPage, $sort);

        return [
            'data' => array_map(fn (Card $card) => $this->cardToArray($card), $cards),
            'paging' => [
                'current' => $page,
                'total' => max(1, (int) ceil($totalResults / $perPage)),
                'per_page' => $perPage,
            ],
            'results' => $totalResults,
            'error' => null,
        ];
    }

    public function suggestCards(string $query): array
    {
        $suggestions = $this->suggestKnownCharacters($query);

        if ($suggestions) {
            return $suggestions;
        }

        return $this->cardRepository->findSuggestions($query);
    }

    public function trendingCards(): array
    {
        return $this->searchCards(null, 1, 'price_highest');
    }

    public function collectionPage(int $page = 1, string $sort = 'relevance'): array
    {
        return $this->searchCards(null, $page, $sort);
    }

    public function apiCollectionPage(int $page = 1, string $sort = 'relevance'): array
    {
        return $this->fetchCards(null, $page, $sort);
    }

    public function marketMovers(int $limit = 8, int $pages = 3): array
    {
        $cards = [];

        for ($page = 1; $page <= $pages; $page++) {
            $response = $this->searchCards(null, $page, 'price_highest');
            $cards = array_merge($cards, $response['data'] ?? []);

            if ($page >= (int) ($response['paging']['total'] ?? 1)) {
                break;
            }
        }

        $cardsById = [];
        foreach ($cards as $card) {
            $id = (int) ($card['id'] ?? 0);

            if ($id > 0) {
                $cardsById[$id] = $card;
            }
        }

        $movers = [];
        foreach ($cardsById as $card) {
            $percent = $this->priceMovementPercent($card);

            if ($percent !== null) {
                $card['movement_percent'] = $percent;
                $movers[] = $card;
            }
        }

        $rising = $movers;
        usort($rising, static fn (array $a, array $b) => $b['movement_percent'] <=> $a['movement_percent']);

        $falling = $movers;
        usort($falling, static fn (array $a, array $b) => $a['movement_percent'] <=> $b['movement_percent']);

        return [
            'rising' => array_slice(array_filter($rising, static fn (array $card) => $card['movement_percent'] > 0), 0, $limit),
            'falling' => array_slice(array_filter($falling, static fn (array $card) => $card['movement_percent'] < 0), 0, $limit),
        ];
    }

    public function findCard(int $id, ?string $searchHint = null): ?array
    {
        $card = $this->cardRepository->findApiIdWithRelations($id);

        return $card ? $this->cardToArray($card) : null;
    }

    public function priceChart(array $card): array
    {
        return array_map(
            fn (string $language) => $this->priceChartForLanguage($card, $language),
            array_keys($this->languageOptions())
        );
    }

    public function priceChartForLanguage(array $card, string $language): array
    {
        $cardmarket = $card['prices']['cardmarket'] ?? [];
        $tcgPlayer = $card['prices']['tcg_player'] ?? [];
        $language = array_key_exists($language, $this->languageOptions()) ? $language : 'all';
        $chart = [
            'key' => $language,
            'label' => $this->languageOptions()[$language],
            'currency' => $cardmarket['currency'] ?? $tcgPlayer['currency'] ?? 'EUR',
            'available' => true,
            'points' => [],
        ];

        $cardEntity = $this->cardRepository->findOneBy(['apiId' => (int) ($card['id'] ?? 0)]);
        $historyRows = $cardEntity ? $this->cardPriceHistoryRepository->findForCard($cardEntity, 30) : [];
        $points = array_values(array_filter(array_map(
            fn (CardPriceHistory $history) => $this->historyPoint($history, $language),
            $historyRows
        )));

        if ($points === [] && $cardEntity && in_array($language, ['fr', 'jp', 'cn', 'kr'], true)) {
            $externalPoint = $this->externalLanguagePoint($cardEntity, $language);
            if ($externalPoint) {
                $points = [$externalPoint];
            }
        }

        if ($points === []) {
            $points = $this->fallbackCurrentPricePoints($card, $language);
        }

        $points = array_values(array_filter($points, static fn (array $point) => $point['value'] !== null));

        if ($points === []) {
            return array_merge($chart, [
                'available' => false,
                'message' => 'No ' . $chart['label'] . ' price history is available for this card yet.',
                'points' => [],
            ]);
        }

        return array_merge($chart, [
            'available' => count(array_filter($points, static fn ($point) => $point['value'] !== null)) > 0,
            'points' => $points,
        ]);
    }

    public function languageOptions(): array
    {
        return [
            'all' => 'English / market',
            'eu' => 'EU only',
            'fr' => 'French',
            'jp' => 'Japanese',
            'cn' => 'Chinese',
            'kr' => 'Korean',
        ];
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, ['relevance', 'price_highest', 'price_lowest'], true) ? $sort : 'relevance';
    }

    private function priceMovementPercent(array $card): ?float
    {
        $cardmarket = $card['prices']['cardmarket'] ?? [];
        $current = $cardmarket['lowest_near_mint'] ?? null;
        $baseline = $cardmarket['30d_average'] ?? null;

        if (!$current || !$baseline || (float) $baseline <= 0) {
            return null;
        }

        return round((((float) $current - (float) $baseline) / (float) $baseline) * 100, 2);
    }

    private function historyPoint(CardPriceHistory $history, string $language): ?array
    {
        $value = match ($language) {
            'all' => $history->getLowestNearMint(),
            'eu' => $history->getLowestNearMintEuOnly(),
            'fr' => $history->getLanguagePrice('fr') ?? $history->getLowestNearMintFr() ?? $history->getLowestNearMintFrEuOnly(),
            'jp', 'cn', 'kr' => $history->getLanguagePrice($language),
            default => null,
        };

        if ($value === null) {
            return null;
        }

        return [
            'label' => $history->getCapturedOn()->format('Y-m-d'),
            'value' => $value,
        ];
    }

    private function externalLanguagePoint(Card $card, string $language): ?array
    {
        $price = $this->cardTraderPriceService->lowestNearMintForLanguage($card, $language);

        if (!$price) {
            return null;
        }

        $capturedOn = new \DateTimeImmutable('today');
        $history = $this->cardPriceHistoryRepository->findOneBy([
            'card' => $card,
            'capturedOn' => $capturedOn,
        ]) ?? new CardPriceHistory();
        $history
            ->setCard($card)
            ->setCapturedOn($capturedOn)
            ->setCurrency($price['currency'] ?? 'EUR')
            ->setLanguagePrice($language, (float) $price['value'], $price['currency'] ?? 'EUR', $price['source'] ?? 'external');
        $this->entityManager->persist($history);
        $this->entityManager->flush();

        return [
            'label' => $capturedOn->format('Y-m-d'),
            'value' => (float) $price['value'],
        ];
    }

    private function fallbackCurrentPricePoints(array $card, string $language): array
    {
        $cardmarket = $card['prices']['cardmarket'] ?? [];

        return match ($language) {
            'all' => [
                ['label' => 'Current lowest NM', 'value' => $cardmarket['lowest_near_mint'] ?? null],
            ],
            'eu' => [
                ['label' => 'Current lowest NM EU', 'value' => $cardmarket['lowest_near_mint_EU_only'] ?? null],
            ],
            'fr' => [
                ['label' => 'Current lowest NM FR', 'value' => $cardmarket['lowest_near_mint_FR'] ?? $cardmarket['lowest_near_mint_FR_EU_only'] ?? null],
            ],
            default => [],
        };
    }

    private function cardToArray(Card $card): array
    {
        $episode = $card->getEpisode();
        $artist = $card->getArtist();
        $price = $this->cardPriceRepository->findOneBy(['card' => $card]);

        return array_merge($card->getRawData() ?? [], [
            'id' => $card->getApiId(),
            'episode' => $episode ? [
                'id' => $episode->getApiId(),
                'name' => $episode->getName(),
                'slug' => $episode->getSlug(),
                'code' => $episode->getCode(),
                'released_at' => $episode->getReleasedAt()?->format('Y-m-d'),
                'logo' => $episode->getLogo(),
            ] : null,
            'artist' => $artist ? [
                'id' => $artist->getApiId(),
                'name' => $artist->getName(),
                'slug' => $artist->getSlug(),
            ] : null,
            'name' => $card->getName(),
            'name_numbered' => $card->getNameNumbered(),
            'slug' => $card->getSlug(),
            'type' => $card->getType(),
            'card_number' => $card->getCardNumber(),
            'hp' => $card->getHp(),
            'rarity' => $card->getRarity(),
            'color' => $card->getColor(),
            'version' => $card->getVersion(),
            'supertype' => $card->getSupertype(),
            'tcgid' => $card->getTcgid(),
            'cardmarket_id' => $card->getCardmarketId(),
            'tcgplayer_id' => $card->getTcgplayerId(),
            'image' => $card->getImage(),
            'tcggo_url' => $card->getTcggoUrl(),
            'links' => $card->getLinks(),
            'prices' => $this->priceToArray($price),
            'updated_at' => $card->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function priceToArray(?CardPrice $price): array
    {
        if (!$price) {
            return [];
        }

        return [
            'cardmarket' => [
                'currency' => $price->getCurrency(),
                'lowest_near_mint' => $price->getLowestNearMint(),
                'lowest_near_mint_EU_only' => $price->getLowestNearMintEuOnly(),
                'lowest_near_mint_FR' => $price->getLowestNearMintFr(),
                'lowest_near_mint_FR_EU_only' => $price->getLowestNearMintFrEuOnly(),
                '7d_average' => $price->getAverage7d(),
                '30d_average' => $price->getAverage30d(),
            ],
            'tcg_player' => [
                'currency' => $price->getCurrency(),
                'market_price' => $price->getTcgplayerMarketPrice(),
            ],
        ];
    }

    private function fetchCards(?string $query, int $page, string $sort): array
    {
        $queryParameters = [
            'page' => max(1, $page),
            'sort' => $this->normalizeSort($sort),
        ];

        if ($query) {
            $queryParameters['search'] = $query;
        }

        try {
            $response = $this->client->request(
                'GET',
                self::API_URL,
                [
                    'headers' => self::API_HEADERS,
                    'query' => $queryParameters,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return $this->emptyResponse('The card API is unavailable right now. Please try again later.');
            }

            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|DecodingExceptionInterface) {
            return $this->emptyResponse('The card API is unavailable right now. Please try again later.');
        }

        $cards = $data['data'] ?? [];

        if (!is_array($cards)) {
            $cards = [];
        }

        return [
            'data' => array_values(array_filter($cards, static fn ($card) => is_array($card) && isset($card['name']))),
            'paging' => $data['paging'] ?? ['current' => 1, 'total' => 1, 'per_page' => 20],
            'results' => $data['results'] ?? count($cards),
            'error' => null,
        ];
    }

    private function buildSearchQueries(string $query): array
    {
        $searches = [];
        $normalized = $this->normalizeName($query);
        $aliases = $this->characterAliases();

        if (isset($aliases[$normalized])) {
            return [$aliases[$normalized]];
        }

        $query = trim($query);
        $searches[] = $query;
        $searches[] = str_replace(' ', '.', preg_replace('/\s+/', ' ', $query) ?? $query);

        if (!str_contains($query, ' ') && !str_contains($query, '.')) {
            $searches[] = 'D.' . $query;
        }

        return array_values(array_unique(array_filter($searches)));
    }

    private function scoreSearchResponse(string $query, array $response): int
    {
        $cards = array_slice($response['data'] ?? [], 0, 20);

        if (!$cards) {
            return -100000;
        }

        $queryName = $this->normalizeName($query);
        $score = min((int) ($response['results'] ?? 0), 500);

        foreach ($cards as $card) {
            $name = $this->normalizeName((string) ($card['name'] ?? ''));
            $numberedName = $this->normalizeName((string) ($card['name_numbered'] ?? ''));

            if ($name === $queryName || $numberedName === $queryName) {
                $score += 100;
            } elseif (str_starts_with($name, $queryName) || str_starts_with($numberedName, $queryName)) {
                $score += 50;
            } elseif (str_contains($name, $queryName) || str_contains($numberedName, $queryName)) {
                $score += 25;
            }
        }

        return $score;
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    private function suggestKnownCharacters(string $query): array
    {
        $normalized = $this->normalizeName($query);

        if (strlen($normalized) < 2) {
            return [];
        }

        $suggestions = [];

        foreach ($this->characterAliases() as $alias => $character) {
            $characterName = $this->normalizeName($character);
            $score = null;

            if (str_starts_with($alias, $normalized)) {
                $score = 0;
            } elseif (str_starts_with($characterName, $normalized)) {
                $score = 1;
            } elseif (str_contains($alias, $normalized)) {
                $score = 2;
            } elseif (str_contains($characterName, $normalized)) {
                $score = 3;
            }

            if ($score !== null && (!isset($suggestions[$character]) || $score < $suggestions[$character])) {
                $suggestions[$character] = $score;
            }
        }

        asort($suggestions);

        return array_slice(array_keys($suggestions), 0, 8);
    }

    private function characterAliases(): array
    {
        return [
            'luffy' => 'Monkey.D.Luffy',
            'monkey' => 'Monkey.D.Luffy',
            'monkeyd' => 'Monkey.D.Luffy',
            'monkeydluffy' => 'Monkey.D.Luffy',
            'dluffy' => 'Monkey.D.Luffy',
            'zoro' => 'Roronoa Zoro',
            'roronoa' => 'Roronoa Zoro',
            'roronoazoro' => 'Roronoa Zoro',
            'nami' => 'Nami',
            'usopp' => 'Usopp',
            'sanji' => 'Sanji',
            'chopper' => 'Tony Tony.Chopper',
            'tonytonychopper' => 'Tony Tony.Chopper',
            'robin' => 'Nico Robin',
            'nico' => 'Nico Robin',
            'nicorobin' => 'Nico Robin',
            'franky' => 'Franky',
            'brook' => 'Brook',
            'jinbe' => 'Jinbe',
            'jimbei' => 'Jinbe',
            'law' => 'Trafalgar Law',
            'trafalgar' => 'Trafalgar Law',
            'trafalgarlaw' => 'Trafalgar Law',
            'trafalgardwaterlaw' => 'Trafalgar.D.Water Law',
            'kid' => 'Eustass Kid',
            'eustass' => 'Eustass Kid',
            'eustasskid' => 'Eustass Kid',
            'ace' => 'Portgas.D.Ace',
            'portgas' => 'Portgas.D.Ace',
            'portgasdace' => 'Portgas.D.Ace',
            'sabo' => 'Sabo',
            'shanks' => 'Shanks',
            'hancock' => 'Boa Hancock',
            'boa' => 'Boa Hancock',
            'boahancock' => 'Boa Hancock',
            'doflamingo' => 'Donquixote Doflamingo',
            'donquixote' => 'Donquixote Doflamingo',
            'donquixotedoflamingo' => 'Donquixote Doflamingo',
            'mihawk' => 'Dracule Mihawk',
            'dracule' => 'Dracule Mihawk',
            'draculemihawk' => 'Dracule Mihawk',
            'crocodile' => 'Crocodile',
            'buggy' => 'Buggy',
            'smoker' => 'Smoker',
            'yamato' => 'Yamato',
            'kaido' => 'Kaido',
            'bigmom' => 'Charlotte Linlin',
            'linlin' => 'Charlotte Linlin',
            'charlottelinlin' => 'Charlotte Linlin',
            'katakuri' => 'Charlotte Katakuri',
            'charlottekatakuri' => 'Charlotte Katakuri',
            'roger' => 'Gol.D.Roger',
            'gol' => 'Gol.D.Roger',
            'gold' => 'Gol.D.Roger',
            'golroger' => 'Gol.D.Roger',
            'goldroger' => 'Gol.D.Roger',
        ];
    }

    private function emptyResponse(string $error): array
    {
        return [
            'data' => [],
            'paging' => ['current' => 1, 'total' => 1, 'per_page' => 20],
            'results' => 0,
            'error' => $error,
        ];
    }
}
