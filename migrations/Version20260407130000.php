<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create card, price, comment and blog topic tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_artist (id INT AUTO_INCREMENT NOT NULL, api_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_CARD_ARTIST_API_ID (api_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card_episode (id INT AUTO_INCREMENT NOT NULL, api_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) DEFAULT NULL, code VARCHAR(50) DEFAULT NULL, released_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', logo VARCHAR(500) DEFAULT NULL, raw_data JSON DEFAULT NULL, UNIQUE INDEX UNIQ_CARD_EPISODE_API_ID (api_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card (id INT AUTO_INCREMENT NOT NULL, episode_id INT DEFAULT NULL, artist_id INT DEFAULT NULL, api_id INT NOT NULL, name VARCHAR(255) NOT NULL, name_numbered VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, type VARCHAR(80) DEFAULT NULL, card_number VARCHAR(50) DEFAULT NULL, hp VARCHAR(50) DEFAULT NULL, rarity VARCHAR(80) DEFAULT NULL, color VARCHAR(80) DEFAULT NULL, version VARCHAR(80) DEFAULT NULL, supertype VARCHAR(80) DEFAULT NULL, tcgid INT DEFAULT NULL, cardmarket_id INT DEFAULT NULL, tcgplayer_id INT DEFAULT NULL, image VARCHAR(500) DEFAULT NULL, tcggo_url VARCHAR(500) DEFAULT NULL, links JSON DEFAULT NULL, raw_data JSON DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CARD_EPISODE (episode_id), INDEX IDX_CARD_ARTIST (artist_id), INDEX idx_card_name (name), INDEX idx_card_slug (slug), UNIQUE INDEX UNIQ_CARD_API_ID (api_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card_price (id INT AUTO_INCREMENT NOT NULL, card_id INT NOT NULL, currency VARCHAR(10) DEFAULT NULL, lowest_near_mint DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_eu_only DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr DOUBLE PRECISION DEFAULT NULL, lowest_near_mint_fr_eu_only DOUBLE PRECISION DEFAULT NULL, average7d DOUBLE PRECISION DEFAULT NULL, average30d DOUBLE PRECISION DEFAULT NULL, tcgplayer_market_price DOUBLE PRECISION DEFAULT NULL, raw_data JSON DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CARD_PRICE_CARD (card_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card_comment (id INT AUTO_INCREMENT NOT NULL, card_id INT NOT NULL, author_name VARCHAR(120) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CARD_COMMENT_CARD (card_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE blog_topic (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_BLOG_TOPIC_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE blog_comment (id INT AUTO_INCREMENT NOT NULL, topic_id INT NOT NULL, author_name VARCHAR(120) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BLOG_COMMENT_TOPIC (topic_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT FK_CARD_EPISODE FOREIGN KEY (episode_id) REFERENCES card_episode (id)');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT FK_CARD_ARTIST FOREIGN KEY (artist_id) REFERENCES card_artist (id)');
        $this->addSql('ALTER TABLE card_price ADD CONSTRAINT FK_CARD_PRICE_CARD FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_comment ADD CONSTRAINT FK_CARD_COMMENT_CARD FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_BLOG_COMMENT_TOPIC FOREIGN KEY (topic_id) REFERENCES blog_topic (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card DROP FOREIGN KEY FK_CARD_EPISODE');
        $this->addSql('ALTER TABLE card DROP FOREIGN KEY FK_CARD_ARTIST');
        $this->addSql('ALTER TABLE card_price DROP FOREIGN KEY FK_CARD_PRICE_CARD');
        $this->addSql('ALTER TABLE card_comment DROP FOREIGN KEY FK_CARD_COMMENT_CARD');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_BLOG_COMMENT_TOPIC');
        $this->addSql('DROP TABLE card_price');
        $this->addSql('DROP TABLE card_comment');
        $this->addSql('DROP TABLE blog_comment');
        $this->addSql('DROP TABLE blog_topic');
        $this->addSql('DROP TABLE card');
        $this->addSql('DROP TABLE card_episode');
        $this->addSql('DROP TABLE card_artist');
    }
}
