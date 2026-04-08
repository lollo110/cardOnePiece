<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add card sync state tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_sync_state (name VARCHAR(80) NOT NULL, last_run_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE card_sync_state');
    }
}
