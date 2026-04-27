<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425143500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align notification table with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE notification RENAME INDEX idx_bf5476ca28e0f56f TO IDX_BF5476CAE92F8F78');
        $this->addSql('ALTER TABLE notification RENAME INDEX idx_bf5476ca81e1820 TO IDX_BF5476CA859B83FF');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification CHANGE read_at read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE notification RENAME INDEX idx_bf5476cae92f8f78 TO IDX_BF5476CA28E0F56F');
        $this->addSql('ALTER TABLE notification RENAME INDEX idx_bf5476ca859b83ff TO IDX_BF5476CA81E1820');
    }
}
