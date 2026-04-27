<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store daily card price history per language.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_price_history (id INT AUTO_INCREMENT NOT NULL, card_id INT NOT NULL, language_key VARCHAR(80) NOT NULL, language_label VARCHAR(120) NOT NULL, average_near_mint_price_cents INT NOT NULL, product_count INT NOT NULL, recorded_on DATE NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_3CE671364ACC9A20 (card_id), INDEX idx_card_price_history_language (language_key), INDEX idx_card_price_history_recorded_on (recorded_on), UNIQUE INDEX uniq_card_price_history_day (card_id, language_key, recorded_on), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_price_history ADD CONSTRAINT FK_3CE671364ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_price_history DROP FOREIGN KEY FK_3CE671364ACC9A20');
        $this->addSql('DROP TABLE card_price_history');
    }
}
