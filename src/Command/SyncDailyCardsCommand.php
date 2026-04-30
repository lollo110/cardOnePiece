<?php

namespace App\Command;

use App\Entity\CardSyncState;
use App\Service\CardImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cards:sync-daily', description: 'Refresh tracked CardTrader prices at most once per day.')]
class SyncDailyCardsCommand extends Command
{
    private const SYNC_NAME = 'daily_cards';

    public function __construct(
        private readonly CardImporter $cardImporter,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Run the sync even if it already ran today')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit tracked cards to refresh', 250)
            ->addOption('refresh-catalog', null, InputOption::VALUE_NONE, 'Explicitly refresh the local catalog cache from CardTrader')
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'When --refresh-catalog is used, limit number of CardTrader expansions to sync')
            ->addOption('flush-every', null, InputOption::VALUE_REQUIRED, 'Flush every N cards', 25);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $state = $this->entityManager->find(CardSyncState::class, self::SYNC_NAME);
        $now = new \DateTimeImmutable();

        if ($state !== null && !$input->getOption('force') && $this->alreadyRanToday($state, $now)) {
            $io->success(sprintf('Card sync already ran today at %s.', $state->getLastRunAt()?->format('Y-m-d H:i:s')));

            return Command::SUCCESS;
        }

        if ($input->getOption('refresh-catalog')) {
            // This explicit mode refreshes the local cache from external CardTrader
            // data. The default daily path below avoids mirroring the full catalog.
            $stats = $this->cardImporter->import(
                $input->getOption('pages') ? (int) $input->getOption('pages') : null,
                max(1, (int) $input->getOption('flush-every')),
                static fn (int $page, int $totalPages, string $label) => $io->writeln(sprintf('Synced expansion %d/%d: %s', $page, $totalPages, $label))
            );
        } else {
            $stats = $this->cardImporter->refreshTrackedPrices(
                max(1, (int) $input->getOption('limit')),
                max(1, (int) $input->getOption('flush-every')),
                static fn (int $current, int $total, string $label) => $io->writeln(sprintf('Refreshed tracked card %d/%d: %s', $current, $total, $label))
            );
        }

        $state = $this->entityManager->find(CardSyncState::class, self::SYNC_NAME) ?? new CardSyncState(self::SYNC_NAME);
        $state->setLastRunAt($now);
        $this->entityManager->persist($state);
        $this->entityManager->flush();

        if ($input->getOption('refresh-catalog')) {
            $io->success(sprintf(
                'Catalog cache sync finished: %d cards checked, %d new, %d updated, %d prices refreshed.',
                $stats['seen'],
                $stats['created'],
                $stats['updated'],
                $stats['prices_updated'] ?? 0,
            ));
        } else {
            $io->success(sprintf(
                'Daily tracked price sync finished: %d cards checked, %d refreshed, %d failed, %d price rows updated.',
                $stats['seen'],
                $stats['refreshed'],
                $stats['failed'],
                $stats['prices_updated'] ?? 0,
            ));
        }

        return Command::SUCCESS;
    }

    private function alreadyRanToday(CardSyncState $state, \DateTimeImmutable $now): bool
    {
        $lastRunAt = $state->getLastRunAt();

        return $lastRunAt !== null && $lastRunAt->format('Y-m-d') === $now->format('Y-m-d');
    }
}
