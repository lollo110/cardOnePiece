<?php

namespace App\Service;

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

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function searchCards(?string $query = null, int $page = 1, string $sort = 'relevance'): array
    {
        try {
            $query = trim((string) $query);

            if ($query === '') {
                return $this->fetchCards(null, $page, $sort);
            }

            $bestResponse = null;
            $bestScore = PHP_INT_MIN;

            foreach ($this->buildSearchQueries($query) as $searchQuery) {
                $response = $this->fetchCards($searchQuery, $page, $sort);

                if ($response['error']) {
                    continue;
                }

                $score = $this->scoreSearchResponse($query, $response);

                if ($score > $bestScore) {
                    $bestResponse = $response;
                    $bestScore = $score;
                }
            }

            return $bestResponse ?? $this->emptyResponse('The card API is unavailable right now. Please try again later.');
        } catch (TransportExceptionInterface|DecodingExceptionInterface) {
            return $this->emptyResponse('The card API is unavailable right now. Please try again later.');
        }
    }

    public function suggestCards(string $query): array
    {
        $suggestions = $this->suggestKnownCharacters($query);

        if ($suggestions) {
            return $suggestions;
        }

        $response = $this->fetchCards($query, 1, 'relevance');
        $suggestions = [];

        foreach ($response['data'] ?? [] as $card) {
            if (!isset($card['name']) || in_array($card['name'], $suggestions, true)) {
                continue;
            }

            $suggestions[] = $card['name'];

            if (count($suggestions) >= 8) {
                break;
            }
        }

        return $suggestions;
    }

    public function trendingCards(): array
    {
        return $this->fetchCards(null, 1, 'price_highest');
    }

    public function collectionPage(int $page = 1, string $sort = 'relevance'): array
    {
        return $this->fetchCards(null, $page, $sort);
    }

    public function marketMovers(int $limit = 8, int $pages = 3): array
    {
        $cards = [];

        for ($page = 1; $page <= $pages; $page++) {
            $response = $this->fetchCards(null, $page, 'price_highest');
            $cards = array_merge($cards, $response['data'] ?? []);
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
        $queries = array_values(array_unique(array_filter([
            $searchHint ? trim($searchHint) : null,
            $searchHint ? preg_replace('/\s+[A-Z]{2}\d{2}-\d{3}.*$/', '', trim($searchHint)) : null,
        ])));

        foreach ($queries as $query) {
            for ($page = 1; $page <= 5; $page++) {
                $response = $this->fetchCards($query, $page, 'relevance');

                foreach ($response['data'] ?? [] as $card) {
                    if ((int) ($card['id'] ?? 0) === $id) {
                        return $card;
                    }
                }

                $paging = $response['paging'] ?? [];
                if ($page >= (int) ($paging['total'] ?? 1)) {
                    break;
                }
            }
        }

        return null;
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

        $points = match ($language) {
            'all' => [
                ['label' => '30d avg', 'value' => $cardmarket['30d_average'] ?? null],
                ['label' => '7d avg', 'value' => $cardmarket['7d_average'] ?? null],
                ['label' => 'Lowest NM', 'value' => $cardmarket['lowest_near_mint'] ?? null],
                ['label' => 'TCGPlayer', 'value' => $tcgPlayer['market_price'] ?? null],
            ],
            'eu' => [
                ['label' => '30d avg', 'value' => $cardmarket['30d_average'] ?? null],
                ['label' => '7d avg', 'value' => $cardmarket['7d_average'] ?? null],
                ['label' => 'Lowest NM EU', 'value' => $cardmarket['lowest_near_mint_EU_only'] ?? null],
            ],
            'fr' => [
                ['label' => '30d avg', 'value' => $cardmarket['30d_average'] ?? null],
                ['label' => '7d avg', 'value' => $cardmarket['7d_average'] ?? null],
                ['label' => 'Lowest NM FR', 'value' => $cardmarket['lowest_near_mint_FR'] ?? null],
                ['label' => 'Lowest NM FR EU', 'value' => $cardmarket['lowest_near_mint_FR_EU_only'] ?? null],
            ],
            default => [],
        };

        if ($points === []) {
            return array_merge($chart, [
                'available' => false,
                'message' => 'No ' . $chart['label'] . ' price data is exposed by the API for this card.',
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
            'all' => 'All languages',
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

    private function fetchCards(?string $query, int $page, string $sort): array
    {
        $queryParameters = [
            'page' => max(1, $page),
            'sort' => $this->normalizeSort($sort),
        ];

        if ($query) {
            $queryParameters['search'] = $query;
        }

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
