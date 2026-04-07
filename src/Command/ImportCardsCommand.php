<?php

namespace App\Command;

use App\Entity\Card;
use App\Entity\CardArtist;
use App\Entity\CardEpisode;
use App\Entity\CardPrice;
use App\Service\CardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cards:import', description: 'Import One Piece cards from the API into the database.')]
class ImportCardsCommand extends Command
{
    private array $cardCache = [];
    private array $episodeCache = [];
    private array $artistCache = [];
    private array $priceCache = [];

    public function __construct(
        private readonly CardService $cardService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Limit number of pages to import')
            ->addOption('flush-every', null, InputOption::VALUE_REQUIRED, 'Flush every N cards', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $firstPage = $this->cardService->collectionPage(1);
        $totalPages = (int) ($firstPage['paging']['total'] ?? 1);
        $pageLimit = $input->getOption('pages') ? min((int) $input->getOption('pages'), $totalPages) : $totalPages;
        $flushEvery = max(1, (int) $input->getOption('flush-every'));
        $count = 0;

        for ($page = 1; $page <= $pageLimit; $page++) {
            $response = $page === 1 ? $firstPage : $this->cardService->collectionPage($page);

            foreach ($response['data'] ?? [] as $payload) {
                $this->upsertCard($payload);
                $count++;

                if ($count % $flushEvery === 0) {
                    $this->entityManager->flush();
                }
            }

            $io->writeln(sprintf('Imported page %d/%d', $page, $pageLimit));
        }

        $this->entityManager->flush();
        $io->success(sprintf('Imported or updated %d cards.', $count));

        return Command::SUCCESS;
    }

    private function upsertCard(array $payload): void
    {
        $apiId = (int) ($payload['id'] ?? 0);

        if ($apiId <= 0) {
            return;
        }

        if (isset($this->cardCache[$apiId])) {
            $card = $this->cardCache[$apiId];
        } else {
            $card = $this->entityManager->getRepository(Card::class)->findOneBy(['apiId' => $apiId]) ?? new Card();
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
    }
}
