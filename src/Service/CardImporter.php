<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\CardEpisode;
use App\Entity\CardLanguagePrice;
use App\Entity\CardPriceHistory;
use Doctrine\ORM\EntityManagerInterface;

class CardImporter
{
    private array $cardCache = [];
    private array $episodeCache = [];
    private array $historyCache = [];
    private array $languagePriceCache = [];

    private int $created = 0;
    private int $updated = 0;
    private int $pricesUpdated = 0;

    public function __construct(
        private readonly CardTraderCatalogService $catalogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function import(?int $pageLimit = null, int $flushEvery = 100, ?callable $onPageImported = null): array
    {
        $this->reset();
        $expansions = $this->catalogService->expansions($pageLimit);
        $totalPages = count($expansions);
        $flushEvery = max(1, $flushEvery);
        $count = 0;

        foreach ($expansions as $index => $expansion) {
            foreach ($this->catalogService->cardsForExpansion($expansion) as $payload) {
                $blueprintId = (int) ($payload['id'] ?? 0);
                $nearMintPriceData = ['overall' => null, 'languages' => []];

                try {
                    $nearMintPriceData = $this->catalogService->nearMintAveragePriceDataForBlueprint($blueprintId);
                } catch (\RuntimeException) {
                    $nearMintPriceData = ['overall' => null, 'languages' => []];
                }

                $this->upsertCard(
                    $payload,
                    $expansion,
                    $nearMintPriceData['overall'],
                    $nearMintPriceData['languages'],
                );
                $count++;
                unset($nearMintPriceData);

                if ($count % $flushEvery === 0) {
                    $this->flushAndReleaseManagedEntities();
                }
            }

            $onPageImported?->__invoke($index + 1, $totalPages, $this->catalogService->expansionLabel($expansion));

            gc_collect_cycles();
        }

        $this->flushAndReleaseManagedEntities();

        return [
            'seen' => $count,
            'created' => $this->created,
            'updated' => $this->updated,
            'prices_updated' => $this->pricesUpdated,
        ];
    }

    public function refreshTrackedPrices(int $limit = 250, int $flushEvery = 25, ?callable $onCardRefreshed = null): array
    {
        $this->reset();
        $cards = $this->entityManager->getRepository(Card::class)->findTrackedForPriceRefresh($limit);
        $flushEvery = max(1, $flushEvery);
        $checked = 0;
        $refreshed = 0;
        $failed = 0;

        foreach ($cards as $card) {
            $checked++;

            try {
                if ($this->refreshCardPrice($card, true, false)) {
                    $refreshed++;
                }
            } catch (\RuntimeException) {
                $failed++;
            }

            $onCardRefreshed?->__invoke($checked, count($cards), $card->getNameNumbered() ?: $card->getName());

            if ($checked % $flushEvery === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        return [
            'seen' => $checked,
            'refreshed' => $refreshed,
            'failed' => $failed,
            'prices_updated' => $this->pricesUpdated,
        ];
    }

    public function refreshCardPrice(Card $card, bool $force = false, bool $flush = true): bool
    {
        if (!$force && !$this->isPriceCacheStale($card)) {
            return false;
        }

        // CardTrader data is external data. The local database keeps a cache
        // and price history for site features; it is not a redistribution of
        // the full API dataset, and prices are only indicative snapshots.
        $nearMintPriceData = $this->catalogService->nearMintAveragePriceDataForBlueprint($card->getApiId());
        $priceUpdatedAt = new \DateTimeImmutable();

        $card
            ->setAverageNearMintPriceCents($nearMintPriceData['overall'])
            ->setPriceUpdatedAt($priceUpdatedAt)
            ->setUpdatedAt($priceUpdatedAt);

        $this->syncLanguagePrices($card, $nearMintPriceData['languages'], $priceUpdatedAt);
        $this->entityManager->persist($card);

        if ($flush) {
            $this->entityManager->flush();
        }

        return true;
    }

    private function isPriceCacheStale(Card $card): bool
    {
        $updatedAt = $card->getPriceUpdatedAt();

        return $updatedAt === null || $updatedAt < (new \DateTimeImmutable('-24 hours'));
    }

    private function reset(): void
    {
        $this->cardCache = [];
        $this->episodeCache = [];
        $this->historyCache = [];
        $this->languagePriceCache = [];
        $this->created = 0;
        $this->updated = 0;
        $this->pricesUpdated = 0;
    }

    private function flushAndReleaseManagedEntities(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->cardCache = [];
        $this->episodeCache = [];
        $this->historyCache = [];
        $this->languagePriceCache = [];
        gc_collect_cycles();
    }

    private function upsertCard(array $payload, array $expansionPayload, ?int $averageNearMintPriceCents = null, array $languagePrices = []): void
    {
        $apiId = (int) ($payload['id'] ?? 0);

        if ($apiId <= 0) {
            return;
        }

        $episode = $this->upsertEpisode($expansionPayload);
        $card = $this->resolveCard($apiId, $payload, $episode);
        $collectorNumber = trim((string) ($payload['fixed_properties']['collector_number'] ?? ''));
        $cardmarketId = $this->primaryCardmarketId($payload);
        $languages = $this->extractLanguages($payload);
        $color = $this->extractColor($payload);
        $priceUpdatedAt = new \DateTimeImmutable();

        $card
            ->setApiId($apiId)
            ->setEpisode($episode)
            ->setArtist(null)
            ->setName((string) ($payload['name'] ?? 'Unknown card'))
            ->setNameNumbered($this->buildDisplayName((string) ($payload['name'] ?? 'Unknown card'), $collectorNumber))
            ->setSlug($this->slugify(sprintf('%s-%s-%s', (string) ($payload['name'] ?? ''), $collectorNumber, (string) ($payload['version'] ?? ''))))
            ->setType(null)
            ->setCardNumber($collectorNumber !== '' ? $collectorNumber : null)
            ->setHp(null)
            ->setRarity($this->normalizeRarity($payload['fixed_properties']['onepiece_rarity'] ?? null))
            ->setColor($color)
            ->setVersion($this->boundedNullableString($payload['version'] ?? null, 255))
            ->setSupertype(null)
            ->setTcgid(null)
            ->setCardmarketId($cardmarketId)
            ->setTcgplayerId(isset($payload['tcg_player_id']) && $payload['tcg_player_id'] !== null ? (int) $payload['tcg_player_id'] : null)
            ->setImage($this->resolveImageUrl($payload))
            ->setTcggoUrl(null)
            ->setLinks(null)
            ->setRawData([
                'source' => 'cardtrader',
                'languages' => $languages,
                'blueprint' => $payload,
                'expansion' => $expansionPayload,
            ])
            ->setUpdatedAt($priceUpdatedAt);

        $this->entityManager->persist($card);

        if ($averageNearMintPriceCents !== null) {
            $card
                ->setAverageNearMintPriceCents($averageNearMintPriceCents)
                ->setPriceUpdatedAt($priceUpdatedAt);
            $this->pricesUpdated++;
        } elseif ($card->getAverageNearMintPriceCents() !== null || $card->getPriceUpdatedAt() !== null) {
            $card
                ->setAverageNearMintPriceCents(null)
                ->setPriceUpdatedAt($priceUpdatedAt);
        }

        $this->syncLanguagePrices($card, $languagePrices, $priceUpdatedAt);
    }

    private function syncLanguagePrices(Card $card, array $languagePrices, \DateTimeImmutable $priceUpdatedAt): void
    {
        $repository = $this->entityManager->getRepository(CardLanguagePrice::class);
        $cacheKey = $this->cardPriceCacheKey($card);
        $existingPrices = [];

        foreach ($repository->findBy(['card' => $card]) as $existingPrice) {
            $existingPrices[$existingPrice->getLanguageKey()] = $existingPrice;
            $this->languagePriceCache[$cacheKey][$existingPrice->getLanguageKey()] = $existingPrice;
        }

        $seenLanguages = [];

        foreach ($languagePrices as $languagePrice) {
            $languageKey = trim((string) ($languagePrice['language_key'] ?? ''));
            $languageLabel = trim((string) ($languagePrice['language_label'] ?? ''));
            $priceCents = $languagePrice['average_near_mint_price_cents'] ?? null;

            if ($languageKey === '' || $languageLabel === '' || !is_numeric($priceCents)) {
                continue;
            }

            $seenLanguages[$languageKey] = true;
            $price = $this->languagePriceCache[$cacheKey][$languageKey]
                ?? $existingPrices[$languageKey]
                ?? $repository->findOneBy(['card' => $card, 'languageKey' => $languageKey])
                ?? new CardLanguagePrice();
            $price
                ->setCard($card)
                ->setLanguageKey($languageKey)
                ->setLanguageLabel($languageLabel)
                ->setAverageNearMintPriceCents((int) $priceCents)
                ->setProductCount(max(1, (int) ($languagePrice['product_count'] ?? 1)))
                ->setUpdatedAt($priceUpdatedAt);

            $this->languagePriceCache[$cacheKey][$languageKey] = $price;
            $this->entityManager->persist($price);
            $this->recordPriceHistory($card, $languageKey, $languageLabel, (int) $priceCents, max(1, (int) ($languagePrice['product_count'] ?? 1)), $priceUpdatedAt);
            $this->pricesUpdated++;
        }

        foreach ($existingPrices as $languageKey => $existingPrice) {
            if (!isset($seenLanguages[$languageKey])) {
                $this->entityManager->remove($existingPrice);
                unset($this->languagePriceCache[$cacheKey][$languageKey]);
            }
        }
    }

    private function cardPriceCacheKey(Card $card): string
    {
        return $card->getId() !== null ? 'card-' . $card->getId() : 'object-' . spl_object_id($card);
    }

    private function recordPriceHistory(Card $card, string $languageKey, string $languageLabel, int $priceCents, int $productCount, \DateTimeImmutable $recordedAt): void
    {
        $recordedOn = $recordedAt->setTime(0, 0);
        $cacheKey = sprintf('%s-%s-%s', $this->cardPriceCacheKey($card), $languageKey, $recordedOn->format('Y-m-d'));
        $repository = $this->entityManager->getRepository(CardPriceHistory::class);
        $history = $this->historyCache[$cacheKey]
            ?? $repository->findOneForDay($card, $languageKey, $recordedOn)
            ?? new CardPriceHistory();

        $history
            ->setCard($card)
            ->setLanguageKey($languageKey)
            ->setLanguageLabel($languageLabel)
            ->setAverageNearMintPriceCents($priceCents)
            ->setProductCount($productCount)
            ->setRecordedOn($recordedOn);

        $this->historyCache[$cacheKey] = $history;
        $this->entityManager->persist($history);
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
            $episode = $this->entityManager->getRepository(CardEpisode::class)->findOneBy(['apiId' => $apiId]);

            if (!$episode && !empty($payload['code'])) {
                $episode = $this->entityManager->getRepository(CardEpisode::class)->findOneBy(['code' => strtoupper((string) $payload['code'])]);
            }

            $episode ??= new CardEpisode();
            $this->episodeCache[$apiId] = $episode;
        }

        $episode
            ->setApiId($apiId)
            ->setName((string) ($payload['name'] ?? 'Unknown set'))
            ->setSlug($this->slugify((string) ($payload['name'] ?? $payload['code'] ?? 'set')))
            ->setCode(!empty($payload['code']) ? strtoupper((string) $payload['code']) : null)
            ->setReleasedAt(null)
            ->setLogo(null)
            ->setRawData($payload);

        $this->entityManager->persist($episode);

        return $episode;
    }

    private function resolveCard(int $apiId, array $payload, ?CardEpisode $episode): Card
    {
        if (isset($this->cardCache[$apiId])) {
            $this->updated++;

            return $this->cardCache[$apiId];
        }

        $repository = $this->entityManager->getRepository(Card::class);
        $card = $repository->findOneBy(['apiId' => $apiId]);

        if (!$card && !empty($payload['tcg_player_id'])) {
            $card = $repository->findOneBy(['tcgplayerId' => (int) $payload['tcg_player_id']]);
        }

        $cardmarketId = $this->primaryCardmarketId($payload);
        if (!$card && $cardmarketId !== null) {
            $card = $repository->findOneBy(['cardmarketId' => $cardmarketId]);
        }

        $collectorNumber = trim((string) ($payload['fixed_properties']['collector_number'] ?? ''));
        if (!$card && $episode && $collectorNumber !== '') {
            $card = $repository->findOneBy([
                'episode' => $episode,
                'cardNumber' => $collectorNumber,
                'name' => (string) ($payload['name'] ?? 'Unknown card'),
            ]);
        }

        if ($card) {
            $this->updated++;
        } else {
            $card = new Card();
            $this->created++;
        }

        $this->cardCache[$apiId] = $card;

        return $card;
    }

    private function extractLanguages(array $payload): array
    {
        foreach ($payload['editable_properties'] ?? [] as $property) {
            if (($property['name'] ?? null) !== 'onepiece_language') {
                continue;
            }

            return array_values(array_map(
                fn (string $language): string => match ($language) {
                    'en' => 'English',
                    'fr' => 'French',
                    'jp' => 'Japanese',
                    'zh-CN', 'zh-TW' => 'Chinese',
                    'kr' => 'Korean',
                    default => strtoupper($language),
                },
                array_filter($property['possible_values'] ?? [], static fn ($value): bool => is_string($value) && $value !== '')
            ));
        }

        return [];
    }

    private function extractColor(array $payload): ?string
    {
        $fixedProperties = $payload['fixed_properties'] ?? [];

        foreach (['onepiece_color', 'color', 'colors'] as $key) {
            $color = $this->normalizeColorValue($fixedProperties[$key] ?? null);

            if ($color !== null) {
                return $color;
            }
        }

        foreach ($payload['editable_properties'] ?? [] as $property) {
            $name = strtolower(trim((string) ($property['name'] ?? '')));

            if ($name === '' || !str_contains($name, 'color')) {
                continue;
            }

            foreach (['value', 'current_value', 'default_value'] as $field) {
                $color = $this->normalizeColorValue($property[$field] ?? null);

                if ($color !== null) {
                    return $color;
                }
            }
        }

        return $this->normalizeColorValue($payload['color'] ?? null);
    }

    private function primaryCardmarketId(array $payload): ?int
    {
        $ids = array_values(array_filter(
            $payload['card_market_ids'] ?? [],
            static fn ($value): bool => is_numeric($value)
        ));

        return $ids !== [] ? (int) $ids[0] : null;
    }

    private function buildDisplayName(string $name, string $collectorNumber): ?string
    {
        $name = trim($name);

        if ($collectorNumber === '') {
            return $name !== '' ? $name : null;
        }

        return sprintf('%s - %s', $name, $collectorNumber);
    }

    private function normalizeRarity(mixed $rarity): ?string
    {
        return match (strtolower(trim((string) $rarity))) {
            'common' => 'Common',
            'uncommon' => 'Uncommon',
            'rare' => 'Rare',
            'super rare' => 'Super Rare',
            'secret rare' => 'Secret Rare',
            'leader' => 'Leader',
            'alternate art' => 'Alternate Art',
            'don!!' => 'DON!!',
            default => $this->nullableString($rarity),
        };
    }

    private function normalizeColorValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $colors = array_values(array_filter(array_map(
                fn (mixed $entry): ?string => $this->normalizeSingleColor($entry),
                $value
            )));

            if ($colors === []) {
                return null;
            }

            return implode(' / ', array_values(array_unique($colors)));
        }

        $rawValue = trim((string) $value);

        if ($rawValue === '') {
            return null;
        }

        $parts = preg_split('/[\/,]/', $rawValue) ?: [];
        $colors = array_values(array_filter(array_map(
            fn (string $part): ?string => $this->normalizeSingleColor($part),
            $parts
        )));

        if ($colors !== []) {
            return implode(' / ', array_values(array_unique($colors)));
        }

        return $this->normalizeSingleColor($rawValue);
    }

    private function normalizeSingleColor(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            'red' => 'Red',
            'green' => 'Green',
            'blue' => 'Blue',
            'purple' => 'Purple',
            'black' => 'Black',
            'yellow' => 'Yellow',
            default => $value !== '' ? ucfirst($value) : null,
        };
    }

    private function resolveImageUrl(array $payload): ?string
    {
        $imageUrl = $this->nullableString($payload['image_url'] ?? null);

        if ($imageUrl !== null) {
            return $imageUrl;
        }

        $relativeUrl = $this->nullableString($payload['image']['preview']['url'] ?? $payload['image']['show']['url'] ?? $payload['image']['url'] ?? null);

        return $relativeUrl !== null ? 'https://cardtrader.com' . $relativeUrl : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function boundedNullableString(mixed $value, int $maxLength): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function slugify(string $value): ?string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : null;
    }
}
