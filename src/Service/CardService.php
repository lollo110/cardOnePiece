<?php

namespace App\Service;

use App\Entity\Card;
use App\Repository\CardRepository;

class CardService
{
    public function __construct(
        private readonly CardRepository $cardRepository,
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
            'price_updated_at' => $card->getPriceUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'source' => 'CardTrader',
            'updated_at' => $card->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

}
