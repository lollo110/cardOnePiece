<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add daily card price history snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_price_history (id INT AUTO_INCREMENT NOT NULL, card_id INT NOT NULL, captured_on DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', currency VARCHAR(10) DEFAULT NULL, lowest_near_mint DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_eu_only DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr_eu_only DOUBLE PRECISION DEFAULT NULL, average7d DOUBLE PRECISION DEFAULT NULL, average30d DOUBLE PRECISION DEFAULT NULL, tcgplayer_market_price DOUBLE PRECISION DEFAULT NULL, raw_data JSON DEFAULT NULL, INDEX IDX_CARD_PRICE_HISTORY_CARD (card_id), UNIQUE INDEX UNIQ_CARD_PRICE_HISTORY_CARD_DAY (card_id, captured_on), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_price_history ADD CONSTRAINT FK_CARD_PRICE_HISTORY_CARD FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO card_price_history (card_id, captured_on, currency, lowest_near_mint, lowest_near_mint_eu_only, lowest_near_mint_fr, lowest_near_mint_fr_eu_only, average7d, average30d, tcgplayer_market_price, raw_data) SELECT card_id, CURRENT_DATE(), currency, lowest_near_mint, lowest_near_mint_eu_only, lowest_near_mint_fr, lowest_near_mint_fr_eu_only, average7d, average30d, tcgplayer_market_price, raw_data FROM card_price');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_price_history DROP FOREIGN KEY FK_CARD_PRICE_HISTORY_CARD');
        $this->addSql('DROP TABLE card_price_history');
    }
}
