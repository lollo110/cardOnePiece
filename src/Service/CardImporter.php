<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\CardEpisode;
use Doctrine\ORM\EntityManagerInterface;

class CardImporter
{
    private array $cardCache = [];
    private array $episodeCache = [];

    private int $created = 0;
    private int $updated = 0;

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
                $this->upsertCard($payload, $expansion);
                $count++;

                if ($count % $flushEvery === 0) {
                    $this->entityManager->flush();
                }
            }

            $onPageImported?->__invoke($index + 1, $totalPages, $this->catalogService->expansionLabel($expansion));
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
        $this->created = 0;
        $this->updated = 0;
    }

    private function upsertCard(array $payload, array $expansionPayload): void
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
            ->setColor(null)
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
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($card);
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
