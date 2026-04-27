<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store CardTrader near-mint average prices per card language.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_language_price (id INT AUTO_INCREMENT NOT NULL, card_id INT NOT NULL, language_key VARCHAR(80) NOT NULL, language_label VARCHAR(120) NOT NULL, average_near_mint_price_cents INT NOT NULL, product_count INT NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F9894C1F4ACC9A20 (card_id), INDEX idx_card_language_price_language (language_key), UNIQUE INDEX uniq_card_language_price (card_id, language_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_language_price ADD CONSTRAINT FK_F9894C1F4ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_language_price DROP FOREIGN KEY FK_F9894C1F4ACC9A20');
        $this->addSql('DROP TABLE card_language_price');
    }
}
