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

    public function searchCards(?string $query = null, int $page = 1, string $sort = 'relevance', ?string $rarity = null): array
    {
        $query = trim((string) $query);
        $rarity = trim((string) $rarity);
        $page = max(1, $page);
        $perPage = 20;
        $sort = $this->normalizeSort($sort);
        $normalizedQuery = $query !== '' ? $query : null;
        $normalizedRarity = $rarity !== '' ? $rarity : null;

        if ($normalizedRarity !== null) {
            $cards = $this->cardRepository->searchAll($normalizedQuery, $sort, $normalizedRarity);
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
        $suggestions = $this->suggestKnownCharacters($query);

        if ($suggestions) {
            return $suggestions;
        }

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
            'source' => 'CardTrader',
            'updated_at' => $card->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
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
}
