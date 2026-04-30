<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:make-admin', description: 'Grant ROLE_ADMIN to an existing user by email or username.')]
class MakeAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('identifier', InputArgument::REQUIRED, 'User email or username');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = trim((string) $input->getArgument('identifier'));
        $user = $this->userRepository->loadUserByIdentifier($identifier);

        if (!$user) {
            $io->error(sprintf('No user found for "%s".', $identifier));

            return Command::FAILURE;
        }

        $roles = array_values(array_filter($user->getRoles(), static fn (string $role): bool => $role !== 'ROLE_USER'));
        $roles[] = 'ROLE_ADMIN';
        $user->setRoles(array_values(array_unique($roles)));
        $this->entityManager->flush();

        $io->success(sprintf('%s is now an admin.', $user->getUsername()));

        return Command::SUCCESS;
    }
}
