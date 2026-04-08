<?php

namespace App\Service;

use App\Entity\Card;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CardTraderPriceService
{
    private const API_URL = 'https://api.cardtrader.com/api/v2';
    private const ONE_PIECE_GAME_ID = 15;

    private ?array $expansions = null;
    private array $blueprintsByExpansion = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $cardTraderApiToken = '',
    ) {
    }

    public function lowestNearMintForLanguage(Card $card, string $language): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $marketLanguages = $this->marketLanguages($language);

        if (!$marketLanguages) {
            return null;
        }

        $blueprintId = $this->findBlueprintId($card);

        if (!$blueprintId) {
            return null;
        }

        $prices = [];

        foreach ($marketLanguages as $marketLanguage) {
            $response = $this->request('/marketplace/products', [
                'blueprint_id' => $blueprintId,
                'language' => $marketLanguage,
            ]);

            foreach ($this->marketplaceProducts($response, $blueprintId) as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $condition = strtolower((string) ($product['properties_hash']['condition'] ?? $product['condition'] ?? ''));
                if ($condition !== '' && !str_contains($condition, 'near mint')) {
                    continue;
                }

                $cents = $product['price']['cents'] ?? $product['price_cents'] ?? null;
                if ($cents !== null) {
                    $prices[] = [
                        'value' => ((float) $cents) / 100,
                        'currency' => $product['price']['currency'] ?? $product['price_currency'] ?? 'EUR',
                    ];
                }
            }
        }

        if (!$prices) {
            return null;
        }

        usort($prices, static fn (array $a, array $b) => $a['value'] <=> $b['value']);

        return [
            'value' => $prices[0]['value'],
            'currency' => $prices[0]['currency'],
            'source' => 'cardtrader',
        ];
    }

    public function isEnabled(): bool
    {
        return trim($this->cardTraderApiToken) !== '';
    }

    private function findBlueprintId(Card $card): ?int
    {
        $expansionId = $this->findExpansionId($card);

        if (!$expansionId) {
            return null;
        }

        $blueprints = $this->blueprintsForExpansion($expansionId);
        $cardmarketId = $card->getCardmarketId();
        $tcgplayerId = $card->getTcgplayerId();
        $cardNumber = $this->normalize($card->getCardNumber() ?? '');
        $cardName = $this->normalize($card->getName());

        foreach ($blueprints as $blueprint) {
            if (!is_array($blueprint)) {
                continue;
            }

            if ($cardmarketId && in_array($cardmarketId, array_map('intval', $blueprint['card_market_ids'] ?? []), true)) {
                return (int) $blueprint['id'];
            }

            if ($tcgplayerId && (int) ($blueprint['tcg_player_id'] ?? 0) === $tcgplayerId) {
                return (int) $blueprint['id'];
            }

            $blueprintName = $this->normalize((string) ($blueprint['name'] ?? ''));
            if ($cardNumber !== '' && str_contains($blueprintName, $cardNumber)) {
                return (int) $blueprint['id'];
            }

            if ($cardName !== '' && $blueprintName === $cardName) {
                return (int) $blueprint['id'];
            }
        }

        return null;
    }

    private function findExpansionId(Card $card): ?int
    {
        $episode = $card->getEpisode();
        $code = $episode?->getCode() ?? '';
        $name = $episode?->getName() ?? '';
        $cardNumberPrefix = preg_replace('/-\d+.*/', '', $card->getCardNumber() ?? '') ?? '';
        $candidates = array_filter(array_unique([
            $this->normalize($cardNumberPrefix),
            $this->normalize($code),
            $this->normalize($name),
        ]));

        foreach ($this->expansions() as $expansion) {
            if (!is_array($expansion)) {
                continue;
            }

            if ((int) ($expansion['game_id'] ?? 0) !== self::ONE_PIECE_GAME_ID) {
                continue;
            }

            $expansionValues = [
                $this->normalize((string) ($expansion['code'] ?? '')),
                $this->normalize((string) ($expansion['name'] ?? '')),
            ];

            foreach ($candidates as $candidate) {
                if ($this->matchesExpansion($candidate, $expansionValues)) {
                    return (int) $expansion['id'];
                }
            }
        }

        return null;
    }

    private function expansions(): array
    {
        if ($this->expansions !== null) {
            return $this->expansions;
        }

        $this->expansions = $this->request('/expansions');

        return $this->expansions;
    }

    private function blueprintsForExpansion(int $expansionId): array
    {
        if (isset($this->blueprintsByExpansion[$expansionId])) {
            return $this->blueprintsByExpansion[$expansionId];
        }

        $this->blueprintsByExpansion[$expansionId] = $this->request('/blueprints/export', [
            'expansion_id' => $expansionId,
        ]);

        return $this->blueprintsByExpansion[$expansionId];
    }

    private function request(string $path, array $query = []): array
    {
        try {
            $response = $this->client->request('GET', self::API_URL . $path, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->cardTraderApiToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private function marketLanguages(string $language): array
    {
        return [
            'fr' => ['fr'],
            'jp' => ['jp'],
            'cn' => ['zh-CN', 'zh-TW'],
            'kr' => ['kr'],
        ][$language] ?? [];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    private function matchesExpansion(string $candidate, array $expansionValues): bool
    {
        if (strlen($candidate) < 3) {
            return false;
        }

        foreach ($expansionValues as $value) {
            if ($value === $candidate) {
                return true;
            }

            if (strlen($candidate) >= 4 && str_contains($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function marketplaceProducts(array $response, int $blueprintId): array
    {
        if (array_is_list($response)) {
            return $response;
        }

        return $response[(string) $blueprintId] ?? $response[$blueprintId] ?? [];
    }
}
