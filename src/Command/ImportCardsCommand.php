<?php

namespace App\Command;

use App\Service\CardImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cards:import', description: 'Import One Piece cards from the API into the database.')]
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
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Limit number of pages to import')
            ->addOption('flush-every', null, InputOption::VALUE_REQUIRED, 'Flush every N cards', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pageLimit = $input->getOption('pages') ? (int) $input->getOption('pages') : null;
        $flushEvery = max(1, (int) $input->getOption('flush-every'));

        $stats = $this->cardImporter->import(
            $pageLimit,
            $flushEvery,
            static fn (int $page, int $totalPages) => $io->writeln(sprintf('Imported page %d/%d', $page, $totalPages))
        );
        $io->success(sprintf(
            'Imported %d cards from the API: %d new, %d updated.',
            $stats['seen'],
            $stats['created'],
            $stats['updated']
        ));

        return Command::SUCCESS;
    }
}
