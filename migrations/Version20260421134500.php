<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421134500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase card version length for longer CardTrader product variants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card MODIFY version VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card MODIFY version VARCHAR(80) DEFAULT NULL');
    }
}
