<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize legacy schema names and create the messenger transport table expected by the current mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blog_comment CHANGE created_at created_at DATETIME NOT NULL, CHANGE moderation_status moderation_status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE blog_comment RENAME INDEX idx_blog_comment_topic TO IDX_7882EFEF1F55203D');
        $this->addSql('ALTER TABLE blog_topic CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE blog_topic RENAME INDEX uniq_blog_topic_slug TO UNIQ_6DAF9DD3989D9B62');
        $this->addSql('ALTER TABLE card CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE card RENAME INDEX uniq_card_api_id TO UNIQ_161498D354963938');
        $this->addSql('ALTER TABLE card RENAME INDEX idx_card_episode TO IDX_161498D3362B62A0');
        $this->addSql('ALTER TABLE card RENAME INDEX idx_card_artist TO IDX_161498D3B7970CF8');
        $this->addSql('ALTER TABLE card_artist RENAME INDEX uniq_card_artist_api_id TO UNIQ_7366C5F354963938');
        $this->addSql('DROP INDEX IDX_CARD_COMMENT_DISCUSSION ON card_comment');
        $this->addSql('ALTER TABLE card_comment CHANGE created_at created_at DATETIME NOT NULL, CHANGE discussion_type discussion_type VARCHAR(20) NOT NULL, CHANGE language language VARCHAR(40) NOT NULL, CHANGE moderation_status moderation_status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE card_comment RENAME INDEX idx_card_comment_card TO IDX_C36ED81A4ACC9A20');
        $this->addSql('ALTER TABLE card_episode CHANGE released_at released_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE card_episode RENAME INDEX uniq_card_episode_api_id TO UNIQ_8AB096AC54963938');
        $this->addSql('ALTER TABLE card_sync_state CHANGE last_run_at last_run_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE blog_comment CHANGE moderation_status moderation_status VARCHAR(20) DEFAULT \'published\' NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE blog_comment RENAME INDEX idx_7882efef1f55203d TO idx_blog_comment_topic');
        $this->addSql('ALTER TABLE blog_topic CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE blog_topic RENAME INDEX uniq_6daf9dd3989d9b62 TO uniq_blog_topic_slug');
        $this->addSql('ALTER TABLE card CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE card RENAME INDEX idx_161498d3b7970cf8 TO idx_card_artist');
        $this->addSql('ALTER TABLE card RENAME INDEX idx_161498d3362b62a0 TO idx_card_episode');
        $this->addSql('ALTER TABLE card RENAME INDEX uniq_161498d354963938 TO uniq_card_api_id');
        $this->addSql('ALTER TABLE card_artist RENAME INDEX uniq_7366c5f354963938 TO uniq_card_artist_api_id');
        $this->addSql('ALTER TABLE card_comment CHANGE moderation_status moderation_status VARCHAR(20) DEFAULT \'published\' NOT NULL, CHANGE language language VARCHAR(40) DEFAULT \'english\' NOT NULL, CHANGE discussion_type discussion_type VARCHAR(20) DEFAULT \'trading\' NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_CARD_COMMENT_DISCUSSION ON card_comment (card_id, discussion_type, language, created_at)');
        $this->addSql('ALTER TABLE card_comment RENAME INDEX idx_c36ed81a4acc9a20 TO idx_card_comment_card');
        $this->addSql('ALTER TABLE card_episode CHANGE released_at released_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE card_episode RENAME INDEX uniq_8ab096ac54963938 TO uniq_card_episode_api_id');
        $this->addSql('ALTER TABLE card_sync_state CHANGE last_run_at last_run_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
