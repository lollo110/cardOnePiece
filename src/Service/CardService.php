<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\CardLanguagePrice;
use App\Entity\CardPriceHistory;
use App\Repository\CardLanguagePriceRepository;
use App\Repository\CardPriceHistoryRepository;
use App\Repository\CardRepository;

class CardService
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly CardLanguagePriceRepository $cardLanguagePriceRepository,
        private readonly CardPriceHistoryRepository $cardPriceHistoryRepository,
    ) {
    }

    public function searchCards(?string $query = null, int $page = 1, string $sort = 'relevance', ?string $rarity = null, ?string $collection = null): array
    {
        $query = trim((string) $query);
        $rarity = trim((string) $rarity);
        $collection = trim((string) $collection);
        $page = max(1, $page);
        $perPage = 20;
        $sort = $this->normalizeSort($sort);
        $normalizedQuery = $query !== '' ? $query : null;
        $normalizedRarity = $rarity !== '' ? $rarity : null;
        $normalizedCollection = $collection !== '' ? $collection : null;

        if ($normalizedRarity !== null || $normalizedCollection !== null) {
            $cards = $this->cardRepository->searchAll($normalizedQuery, $sort, $normalizedRarity, $normalizedCollection);
            $totalResults = count($cards);

            return [
                'data' => array_map(fn (Card $card) => $this->cardToArray($card), $cards),
                'paging' => [
                    'current' => 1,
                    'total' => 1,
                    'per_page' => max(1, $totalResults),
                ],
                'results' => $totalResults,
                'error' => null,
            ];
        }

        $totalResults = $this->cardRepository->countSearch($normalizedQuery);
        $cards = $this->cardRepository->searchPage($normalizedQuery, $page, $perPage, $sort);

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

    public function rarityOptions(?string $query = null): array
    {
        $query = trim((string) $query);

        return $this->cardRepository->findRarityOptions($query !== '' ? $query : null);
    }

    public function collectionOptions(?string $query = null): array
    {
        $query = trim((string) $query);

        return $this->cardRepository->findCollectionOptions($query !== '' ? $query : null);
    }

    public function recentCards(int $limit = 8): array
    {
        return array_map(
            fn (Card $card) => $this->cardToArray($card),
            $this->cardRepository->findLatest($limit)
        );
    }

    public function mostValuableCards(int $limit = 16): array
    {
        return array_map(
            fn (Card $card) => $this->cardToArray($card),
            $this->cardRepository->findMostValuable($limit)
        );
    }

    public function collectionPage(int $page = 1, string $sort = 'relevance'): array
    {
        return $this->searchCards(null, $page, $sort);
    }

    public function suggestCards(string $query): array
    {
        return $this->cardRepository->findSuggestions($query);
    }

    public function findCard(int $id, ?string $searchHint = null): ?array
    {
        $card = $this->cardRepository->findApiIdWithRelations($id);

        return $card ? $this->cardToArray($card) : null;
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, ['relevance', 'recent', 'name_desc'], true) ? $sort : 'relevance';
    }

    private function cardToArray(Card $card): array
    {
        $episode = $card->getEpisode();
        $artist = $card->getArtist();
        $rawData = $card->getRawData() ?? [];

        return [
            'id' => $card->getApiId(),
            'episode' => $episode ? [
                'id' => $episode->getApiId(),
                'name' => $episode->getName(),
                'slug' => $episode->getSlug(),
                'code' => $episode->getCode(),
                'released_at' => $episode->getReleasedAt()?->format('Y-m-d'),
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
            'languages' => $rawData['languages'] ?? [],
            'average_near_mint_price_cents' => $card->getAverageNearMintPriceCents(),
            'average_near_mint_price' => $card->getAverageNearMintPriceCents() !== null
                ? number_format($card->getAverageNearMintPriceCents() / 100, 2, '.', '')
                : null,
            'language_prices' => array_map(
                fn (CardLanguagePrice $price): array => [
                    'language_key' => $price->getLanguageKey(),
                    'language_label' => $price->getLanguageLabel(),
                    'average_near_mint_price_cents' => $price->getAverageNearMintPriceCents(),
                    'average_near_mint_price' => number_format($price->getAverageNearMintPriceCents() / 100, 2, '.', ''),
                    'product_count' => $price->getProductCount(),
                    'updated_at' => $price->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $this->cardLanguagePriceRepository->findForCard($card)
            ),
            'price_history' => $this->priceHistoryToArray($card),
            'price_updated_at' => $card->getPriceUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'source' => 'CardTrader',
            'updated_at' => $card->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function priceHistoryToArray(Card $card): array
    {
        $history = [];

        foreach ($this->cardPriceHistoryRepository->findRecentForCard($card) as $row) {
            $history[$row->getLanguageKey()] ??= [
                'language_key' => $row->getLanguageKey(),
                'language_label' => $row->getLanguageLabel(),
                'points' => [],
            ];

            $history[$row->getLanguageKey()]['points'][] = [
                'date' => $row->getRecordedOn()->format('Y-m-d'),
                'price' => number_format($row->getAverageNearMintPriceCents() / 100, 2, '.', ''),
                'price_cents' => $row->getAverageNearMintPriceCents(),
                'product_count' => $row->getProductCount(),
            ];
        }

        return array_values($history);
    }

}
