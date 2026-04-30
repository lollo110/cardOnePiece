<?php

namespace App\Command;

use App\Service\CardImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cards:import', description: 'Manually refresh the local CardTrader card cache.')]
class ImportCardsCommand extends Command
{
    public function __construct(
        private readonly CardImporter $cardImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Limit number of CardTrader expansions to import')
            ->addOption('flush-every', null, InputOption::VALUE_REQUIRED, 'Flush every N cards', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pageLimit = $input->getOption('pages') ? (int) $input->getOption('pages') : null;
        $flushEvery = max(1, (int) $input->getOption('flush-every'));

        // CardTrader data is external. This command is an explicit cache
        // maintenance tool, not the default daily job and not a redistribution
        // pipeline for the full upstream dataset.
        $stats = $this->cardImporter->import(
            $pageLimit,
            $flushEvery,
            static fn (int $page, int $totalPages, string $label) => $io->writeln(sprintf('Imported expansion %d/%d: %s', $page, $totalPages, $label))
        );
        $io->success(sprintf(
            'Imported %d cards from CardTrader: %d new, %d updated.',
            $stats['seen'],
            $stats['created'],
            $stats['updated']
        ));

        return Command::SUCCESS;
    }
}
