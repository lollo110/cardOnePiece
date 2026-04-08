<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408094500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external language price snapshots to card price history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_price_history ADD language_prices JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_price_history DROP language_prices');
    }
}
