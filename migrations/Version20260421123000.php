<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop price tables now that the app uses CardTrader only for the card catalog.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE card_price_history');
        $this->addSql('DROP TABLE card_price');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_price (id INT AUTO_INCREMENT NOT NULL, card_id INT NOT NULL, currency VARCHAR(10) DEFAULT NULL, lowest_near_mint DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_eu_only DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr_eu_only DOUBLE PRECISION DEFAULT NULL, average7d DOUBLE PRECISION DEFAULT NULL, average30d DOUBLE PRECISION DEFAULT NULL, tcgplayer_market_price DOUBLE PRECISION DEFAULT NULL, raw_data JSON DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CARD_PRICE_CARD (card_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card_price_history (id INT AUTO_INCREMENT NOT NULL, card_id INT NOT NULL, captured_on DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', currency VARCHAR(10) DEFAULT NULL, lowest_near_mint DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_eu_only DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr_eu_only DOUBLE PRECISION DEFAULT NULL, average7d DOUBLE PRECISION DEFAULT NULL, average30d DOUBLE PRECISION DEFAULT NULL, tcgplayer_market_price DOUBLE PRECISION DEFAULT NULL, raw_data JSON DEFAULT NULL, language_prices JSON DEFAULT NULL, INDEX IDX_CARD_PRICE_HISTORY_CARD (card_id), UNIQUE INDEX UNIQ_CARD_PRICE_HISTORY_CARD_DAY (card_id, captured_on), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_price ADD CONSTRAINT FK_CARD_PRICE_CARD FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_price_history ADD CONSTRAINT FK_CARD_PRICE_HISTORY_CARD FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
    }
}
