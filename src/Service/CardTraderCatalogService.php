<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CardTraderCatalogService
{
    private const API_URL = 'https://api.cardtrader.com/api/v2';
    private const ONE_PIECE_GAME_ID = 15;
    private const CARD_CATEGORIES = [192, 255];

    private ?array $expansions = null;
    private float $lastMarketplaceRequestAt = 0.0;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $cardTraderApiToken = '',
    ) {
    }

    public function expansions(?int $limit = null): array
    {
        if ($this->expansions === null) {
            $expansions = array_values(array_filter(
                $this->requestList('/expansions'),
                static fn (array $expansion): bool => (int) ($expansion['game_id'] ?? 0) === self::ONE_PIECE_GAME_ID
            ));

            usort($expansions, static fn (array $a, array $b): int => strcmp(
                strtolower((string) ($a['code'] ?? $a['name'] ?? '')),
                strtolower((string) ($b['code'] ?? $b['name'] ?? ''))
            ));

            $this->expansions = $expansions;
        }

        return $limit !== null ? array_slice($this->expansions, 0, max(0, $limit)) : $this->expansions;
    }

    public function cardsForExpansion(array $expansion): array
    {
        $expansionId = (int) ($expansion['id'] ?? 0);

        if ($expansionId <= 0) {
            return [];
        }

        return array_values(array_filter(
            $this->requestList('/blueprints/export', ['expansion_id' => $expansionId], true),
            static fn (array $blueprint): bool => in_array((int) ($blueprint['category_id'] ?? 0), self::CARD_CATEGORIES, true)
        ));
    }

    public function nearMintAveragePricesForExpansion(array $expansion): array
    {
        return $this->nearMintAveragePriceDataForExpansion($expansion)['overall'];
    }

    public function nearMintAveragePriceDataForExpansion(array $expansion): array
    {
        $expansionId = (int) ($expansion['id'] ?? 0);

        if ($expansionId <= 0) {
            return ['overall' => [], 'languages' => []];
        }

        $this->waitForMarketplaceRateLimit();
        $data = $this->requestData('/marketplace/products', ['expansion_id' => $expansionId], true);
        $averagesByBlueprint = [];
        $languageAveragesByBlueprint = [];

        foreach ($data as $blueprintId => $products) {
            if (!is_array($products)) {
                continue;
            }

            $normalizedBlueprintId = is_numeric($blueprintId) ? (int) $blueprintId : 0;

            if ($normalizedBlueprintId <= 0) {
                continue;
            }

            $prices = [];
            $pricesByLanguage = [];

            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $condition = strtolower(trim((string) ($product['properties_hash']['condition'] ?? $product['properties']['condition'] ?? '')));

                if ($condition !== 'near mint') {
                    continue;
                }

                $priceCents = $product['price_cents'] ?? $product['price']['cents'] ?? null;

                if (!is_numeric($priceCents)) {
                    continue;
                }

                $prices[] = (int) $priceCents;
                $language = $this->productLanguage($product);

                if ($language !== null) {
                    $pricesByLanguage[$language['key']]['label'] = $language['label'];
                    $pricesByLanguage[$language['key']]['prices'][] = (int) $priceCents;
                }
            }

            if ($prices !== []) {
                $averagesByBlueprint[$normalizedBlueprintId] = (int) round(array_sum($prices) / count($prices));
            }

            foreach ($pricesByLanguage as $languageKey => $languageData) {
                $languagePrices = $languageData['prices'] ?? [];

                if ($languagePrices === []) {
                    continue;
                }

                $languageAveragesByBlueprint[$normalizedBlueprintId][$languageKey] = [
                    'language_key' => $languageKey,
                    'language_label' => $languageData['label'],
                    'average_near_mint_price_cents' => (int) round(array_sum($languagePrices) / count($languagePrices)),
                    'product_count' => count($languagePrices),
                ];
            }
        }

        unset($data);

        return [
            'overall' => $averagesByBlueprint,
            'languages' => $languageAveragesByBlueprint,
        ];
    }

    public function nearMintAveragePriceDataForBlueprint(int $blueprintId): array
    {
        if ($blueprintId <= 0) {
            return ['overall' => null, 'languages' => []];
        }

        $this->waitForMarketplaceRateLimit();
        $data = $this->requestData('/marketplace/products', ['blueprint_id' => $blueprintId], true);
        $products = $this->productsFromMarketplaceResponse($data, $blueprintId);

        unset($data);

        return $this->averagePriceDataForProducts($products);
    }

    public function expansionLabel(array $expansion): string
    {
        $code = strtoupper((string) ($expansion['code'] ?? ''));
        $name = trim((string) ($expansion['name'] ?? ''));

        if ($code !== '' && $name !== '') {
            return sprintf('%s - %s', $code, $name);
        }

        return $name !== '' ? $name : 'Unknown expansion';
    }

    private function requestList(string $path, array $query = [], bool $allowNotFound = false): array
    {
        $data = $this->requestData($path, $query, $allowNotFound);

        return array_values(array_filter($data, static fn ($row): bool => is_array($row)));
    }

    private function requestData(string $path, array $query = [], bool $allowNotFound = false): array
    {
        if (trim($this->cardTraderApiToken) === '') {
            throw new \RuntimeException('CardTrader API token is missing. Set CARDTRADER_API_TOKEN before importing cards.');
        }

        try {
            $response = $this->client->request('GET', self::API_URL . $path, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->cardTraderApiToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('CardTrader is unavailable right now. Please try again later.', 0, $exception);
        }

        if ($statusCode === 401 || $statusCode === 403) {
            throw new \RuntimeException('CardTrader rejected the API token. Please check CARDTRADER_API_TOKEN.');
        }

        if ($allowNotFound && $statusCode === 404) {
            return [];
        }

        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf('CardTrader returned HTTP %d while syncing cards.', $statusCode));
        }

        $data = $response->toArray(false);

        if (!is_array($data)) {
            throw new \RuntimeException('CardTrader returned an unexpected response.');
        }

        return $data;
    }

    private function waitForMarketplaceRateLimit(): void
    {
        $elapsed = microtime(true) - $this->lastMarketplaceRequestAt;
        $minimumDelay = 0.13;

        if ($this->lastMarketplaceRequestAt > 0 && $elapsed < $minimumDelay) {
            usleep((int) (($minimumDelay - $elapsed) * 1_000_000));
        }

        $this->lastMarketplaceRequestAt = microtime(true);
    }

    private function productsFromMarketplaceResponse(array $data, int $blueprintId): array
    {
        if (isset($data[$blueprintId]) && is_array($data[$blueprintId])) {
            return array_values(array_filter($data[$blueprintId], static fn ($product): bool => is_array($product)));
        }

        if (isset($data[(string) $blueprintId]) && is_array($data[(string) $blueprintId])) {
            return array_values(array_filter($data[(string) $blueprintId], static fn ($product): bool => is_array($product)));
        }

        return array_values(array_filter($data, static fn ($product): bool => is_array($product)));
    }

    private function averagePriceDataForProducts(array $products): array
    {
        $prices = [];
        $pricesByLanguage = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $condition = strtolower(trim((string) ($product['properties_hash']['condition'] ?? $product['properties']['condition'] ?? '')));

            if ($condition !== 'near mint') {
                continue;
            }

            $priceCents = $product['price_cents'] ?? $product['price']['cents'] ?? null;

            if (!is_numeric($priceCents)) {
                continue;
            }

            $priceCents = (int) $priceCents;
            $prices[] = $priceCents;
            $language = $this->productLanguage($product);

            if ($language === null) {
                continue;
            }

            $pricesByLanguage[$language['key']]['label'] = $language['label'];
            $pricesByLanguage[$language['key']]['prices'][] = $priceCents;
        }

        $languageAverages = [];

        foreach ($pricesByLanguage as $languageKey => $languageData) {
            $languagePrices = $languageData['prices'] ?? [];

            if ($languagePrices === []) {
                continue;
            }

            $languageAverages[$languageKey] = [
                'language_key' => $languageKey,
                'language_label' => $languageData['label'],
                'average_near_mint_price_cents' => (int) round(array_sum($languagePrices) / count($languagePrices)),
                'product_count' => count($languagePrices),
            ];
        }

        return [
            'overall' => $prices !== [] ? (int) round(array_sum($prices) / count($prices)) : null,
            'languages' => $languageAverages,
        ];
    }

    /**
     * @return array{key: string, label: string}|null
     */
    private function productLanguage(array $product): ?array
    {
        $language = $this->productProperty($product, ['onepiece_language', 'language', 'card_language']);

        if ($language === null) {
            return null;
        }

        return $this->normalizeLanguage((string) $language);
    }

    private function productProperty(array $product, array $names): mixed
    {
        foreach (['properties_hash', 'properties', 'fixed_properties'] as $propertyGroup) {
            $properties = $product[$propertyGroup] ?? null;

            if (!is_array($properties)) {
                continue;
            }

            foreach ($names as $name) {
                if (array_key_exists($name, $properties)) {
                    return $properties[$name];
                }
            }

            foreach ($properties as $property) {
                if (!is_array($property)) {
                    continue;
                }

                $propertyName = strtolower(trim((string) ($property['name'] ?? $property['key'] ?? '')));

                if (!in_array($propertyName, $names, true)) {
                    continue;
                }

                return $property['value'] ?? $property['current_value'] ?? $property['default_value'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string}|null
     */
    private function normalizeLanguage(string $language): ?array
    {
        $normalized = strtolower(trim($language));

        if ($normalized === '') {
            return null;
        }

        $label = match ($normalized) {
            'en', 'eng', 'english' => 'English',
            'fr', 'fra', 'fre', 'french' => 'French',
            'jp', 'ja', 'jpn', 'japanese' => 'Japanese',
            'zh', 'zh-cn', 'zh-tw', 'cn', 'chinese', 'simplified chinese', 'traditional chinese' => 'Chinese',
            'kr', 'ko', 'kor', 'korean' => 'Korean',
            default => ucwords(str_replace(['-', '_'], ' ', $normalized)),
        };

        $key = preg_replace('/[^a-z0-9]+/', '-', strtolower($label)) ?? '';
        $key = trim($key, '-');

        return [
            'key' => $key !== '' ? $key : $normalized,
            'label' => $label,
        ];
    }
}
