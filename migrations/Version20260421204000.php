<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421204000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align blog forum indexes with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment RENAME INDEX IDX_BLOG_COMMENT_THREAD TO IDX_7882EFEFE2904019');
        $this->addSql('DROP INDEX IDX_BLOG_THREAD_ROOM_ACTIVITY ON blog_thread');
        $this->addSql('ALTER TABLE blog_thread RENAME INDEX IDX_BLOG_THREAD_TOPIC TO IDX_A46FE9421F55203D');
        $this->addSql('ALTER TABLE blog_thread RENAME INDEX IDX_BLOG_THREAD_AUTHOR TO IDX_A46FE942E2544CD6');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment RENAME INDEX IDX_7882EFEFE2904019 TO IDX_BLOG_COMMENT_THREAD');
        $this->addSql('CREATE INDEX IDX_BLOG_THREAD_ROOM_ACTIVITY ON blog_thread (topic_id, moderation_status, last_activity_at)');
        $this->addSql('ALTER TABLE blog_thread RENAME INDEX IDX_A46FE9421F55203D TO IDX_BLOG_THREAD_TOPIC');
        $this->addSql('ALTER TABLE blog_thread RENAME INDEX IDX_A46FE942E2544CD6 TO IDX_BLOG_THREAD_AUTHOR');
    }
}
