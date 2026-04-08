<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\CardArtist;
use App\Entity\CardEpisode;
use App\Entity\CardPrice;
use App\Entity\CardPriceHistory;
use Doctrine\ORM\EntityManagerInterface;

class CardImporter
{
    private array $cardCache = [];
    private array $episodeCache = [];
    private array $artistCache = [];
    private array $priceCache = [];

    private int $created = 0;
    private int $updated = 0;

    public function __construct(
        private readonly CardService $cardService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function import(?int $pageLimit = null, int $flushEvery = 100, ?callable $onPageImported = null): array
    {
        $this->reset();

        $firstPage = $this->cardService->apiCollectionPage(1);
        $this->guardApiResponse($firstPage);

        $totalPages = (int) ($firstPage['paging']['total'] ?? 1);
        $pageLimit = $pageLimit ? min($pageLimit, $totalPages) : $totalPages;
        $flushEvery = max(1, $flushEvery);
        $count = 0;

        for ($page = 1; $page <= $pageLimit; $page++) {
            $response = $page === 1 ? $firstPage : $this->cardService->apiCollectionPage($page);
            $this->guardApiResponse($response);

            foreach ($response['data'] ?? [] as $payload) {
                $this->upsertCard($payload);
                $count++;

                if ($count % $flushEvery === 0) {
                    $this->entityManager->flush();
                }
            }

            $onPageImported?->__invoke($page, $pageLimit);
        }

        $this->entityManager->flush();

        return [
            'seen' => $count,
            'created' => $this->created,
            'updated' => $this->updated,
        ];
    }

    private function reset(): void
    {
        $this->cardCache = [];
        $this->episodeCache = [];
        $this->artistCache = [];
        $this->priceCache = [];
        $this->created = 0;
        $this->updated = 0;
    }

    private function guardApiResponse(array $response): void
    {
        if ($response['error'] ?? null) {
            throw new \RuntimeException((string) $response['error']);
        }
    }

    private function upsertCard(array $payload): void
    {
        $apiId = (int) ($payload['id'] ?? 0);

        if ($apiId <= 0) {
            return;
        }

        if (isset($this->cardCache[$apiId])) {
            $card = $this->cardCache[$apiId];
            $this->updated++;
        } else {
            $card = $this->entityManager->getRepository(Card::class)->findOneBy(['apiId' => $apiId]);

            if ($card) {
                $this->updated++;
            } else {
                $card = new Card();
                $this->created++;
            }

            $this->cardCache[$apiId] = $card;
        }

        $card
            ->setApiId($apiId)
            ->setEpisode($this->upsertEpisode($payload['episode'] ?? null))
            ->setArtist($this->upsertArtist($payload['artist'] ?? null))
            ->setName((string) ($payload['name'] ?? 'Unknown card'))
            ->setNameNumbered($payload['name_numbered'] ?? null)
            ->setSlug($payload['slug'] ?? null)
            ->setType($payload['type'] ?? null)
            ->setCardNumber($payload['card_number'] ?? null)
            ->setHp(isset($payload['hp']) ? (string) $payload['hp'] : null)
            ->setRarity($payload['rarity'] ?? null)
            ->setColor($payload['color'] ?? null)
            ->setVersion($payload['version'] ?? null)
            ->setSupertype($payload['supertype'] ?? null)
            ->setTcgid(isset($payload['tcgid']) ? (int) $payload['tcgid'] : null)
            ->setCardmarketId(isset($payload['cardmarket_id']) ? (int) $payload['cardmarket_id'] : null)
            ->setTcgplayerId(isset($payload['tcgplayer_id']) ? (int) $payload['tcgplayer_id'] : null)
            ->setImage($payload['image'] ?? null)
            ->setTcggoUrl($payload['tcggo_url'] ?? null)
            ->setLinks($payload['links'] ?? null)
            ->setRawData($payload)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($card);
        $this->upsertPrice($card, $payload['prices'] ?? []);
    }

    private function upsertEpisode(?array $payload): ?CardEpisode
    {
        if (!$payload || !isset($payload['id'])) {
            return null;
        }

        $apiId = (int) $payload['id'];

        if (isset($this->episodeCache[$apiId])) {
            $episode = $this->episodeCache[$apiId];
        } else {
            $episode = $this->entityManager->getRepository(CardEpisode::class)->findOneBy(['apiId' => $apiId]) ?? new CardEpisode();
            $this->episodeCache[$apiId] = $episode;
        }

        $episode
            ->setApiId($apiId)
            ->setName((string) ($payload['name'] ?? 'Unknown set'))
            ->setSlug($payload['slug'] ?? null)
            ->setCode($payload['code'] ?? null)
            ->setLogo($payload['logo'] ?? null)
            ->setRawData($payload);

        if (!empty($payload['released_at'])) {
            $episode->setReleasedAt(new \DateTimeImmutable($payload['released_at']));
        }

        $this->entityManager->persist($episode);

        return $episode;
    }

    private function upsertArtist(?array $payload): ?CardArtist
    {
        if (!$payload || !isset($payload['id'])) {
            return null;
        }

        $apiId = (int) $payload['id'];

        if (isset($this->artistCache[$apiId])) {
            $artist = $this->artistCache[$apiId];
        } else {
            $artist = $this->entityManager->getRepository(CardArtist::class)->findOneBy(['apiId' => $apiId]) ?? new CardArtist();
            $this->artistCache[$apiId] = $artist;
        }

        $artist
            ->setApiId($apiId)
            ->setName((string) ($payload['name'] ?? 'Unknown artist'))
            ->setSlug($payload['slug'] ?? null);

        $this->entityManager->persist($artist);

        return $artist;
    }

    private function upsertPrice(Card $card, array $payload): void
    {
        $apiId = $card->getApiId();

        if (isset($this->priceCache[$apiId])) {
            $price = $this->priceCache[$apiId];
        } else {
            $price = $this->entityManager->getRepository(CardPrice::class)->findOneBy(['card' => $card]) ?? new CardPrice();
            $this->priceCache[$apiId] = $price;
        }

        $cardmarket = $payload['cardmarket'] ?? [];
        $tcgPlayer = $payload['tcg_player'] ?? [];

        $price
            ->setCard($card)
            ->setCurrency($cardmarket['currency'] ?? $tcgPlayer['currency'] ?? null)
            ->setLowestNearMint(isset($cardmarket['lowest_near_mint']) ? (float) $cardmarket['lowest_near_mint'] : null)
            ->setLowestNearMintEuOnly(isset($cardmarket['lowest_near_mint_EU_only']) ? (float) $cardmarket['lowest_near_mint_EU_only'] : null)
            ->setLowestNearMintFr(isset($cardmarket['lowest_near_mint_FR']) ? (float) $cardmarket['lowest_near_mint_FR'] : null)
            ->setLowestNearMintFrEuOnly(isset($cardmarket['lowest_near_mint_FR_EU_only']) ? (float) $cardmarket['lowest_near_mint_FR_EU_only'] : null)
            ->setAverage7d(isset($cardmarket['7d_average']) ? (float) $cardmarket['7d_average'] : null)
            ->setAverage30d(isset($cardmarket['30d_average']) ? (float) $cardmarket['30d_average'] : null)
            ->setTcgplayerMarketPrice(isset($tcgPlayer['market_price']) ? (float) $tcgPlayer['market_price'] : null)
            ->setRawData($payload)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($price);
        $this->upsertPriceHistory($card, $price, $payload);
    }

    private function upsertPriceHistory(Card $card, CardPrice $price, array $payload): void
    {
        $capturedOn = new \DateTimeImmutable('today');
        $history = $this->entityManager->getRepository(CardPriceHistory::class)->findOneBy([
            'card' => $card,
            'capturedOn' => $capturedOn,
        ]) ?? new CardPriceHistory();

        $history
            ->setCard($card)
            ->setCapturedOn($capturedOn)
            ->setCurrency($price->getCurrency())
            ->setLowestNearMint($price->getLowestNearMint())
            ->setLowestNearMintEuOnly($price->getLowestNearMintEuOnly())
            ->setLowestNearMintFr($price->getLowestNearMintFr())
            ->setLowestNearMintFrEuOnly($price->getLowestNearMintFrEuOnly())
            ->setAverage7d($price->getAverage7d())
            ->setAverage30d($price->getAverage30d())
            ->setTcgplayerMarketPrice($price->getTcgplayerMarketPrice())
            ->setRawData($payload);

        $this->entityManager->persist($history);
    }
}
