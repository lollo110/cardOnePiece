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

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $cardTraderApiToken = '',
    ) {
    }

    public function expansions(?int $limit = null): array
    {
        if ($this->expansions === null) {
            $expansions = array_values(array_filter(
                $this->request('/expansions'),
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
            $this->request('/blueprints/export', ['expansion_id' => $expansionId], true),
            static fn (array $blueprint): bool => in_array((int) ($blueprint['category_id'] ?? 0), self::CARD_CATEGORIES, true)
        ));
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

    private function request(string $path, array $query = [], bool $allowNotFound = false): array
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

        return array_values(array_filter($data, static fn ($row): bool => is_array($row)));
    }
}
