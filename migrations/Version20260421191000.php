<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the current CardTrader near-mint average price on cards.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card ADD average_near_mint_price_cents INT DEFAULT NULL, ADD price_updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card DROP average_near_mint_price_cents, DROP price_updated_at');
    }
}
