<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Turn blog rooms into forum rooms with titled discussion topics and replies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blog_thread (id INT AUTO_INCREMENT NOT NULL, topic_id INT NOT NULL, author_user_id INT DEFAULT NULL, author_name VARCHAR(120) NOT NULL, title VARCHAR(160) NOT NULL, content LONGTEXT NOT NULL, moderation_status VARCHAR(20) NOT NULL, moderation_reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, INDEX IDX_BLOG_THREAD_TOPIC (topic_id), INDEX IDX_BLOG_THREAD_AUTHOR (author_user_id), INDEX IDX_BLOG_THREAD_ROOM_ACTIVITY (topic_id, moderation_status, last_activity_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blog_thread ADD CONSTRAINT FK_BLOG_THREAD_TOPIC FOREIGN KEY (topic_id) REFERENCES blog_topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_thread ADD CONSTRAINT FK_BLOG_THREAD_AUTHOR FOREIGN KEY (author_user_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE blog_comment ADD thread_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_BLOG_COMMENT_THREAD ON blog_comment (thread_id)');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_BLOG_COMMENT_THREAD FOREIGN KEY (thread_id) REFERENCES blog_thread (id) ON DELETE CASCADE');

        $this->addSql("INSERT INTO blog_thread (topic_id, author_user_id, author_name, title, content, moderation_status, moderation_reason, created_at, last_activity_at)
            SELECT
                topic_id,
                author_user_id,
                author_name,
                CASE
                    WHEN CHAR_LENGTH(TRIM(content)) <= 80 THEN TRIM(content)
                    ELSE CONCAT(SUBSTRING(TRIM(content), 1, 77), '...')
                END AS title,
                content,
                moderation_status,
                moderation_reason,
                created_at,
                created_at
            FROM blog_comment");

        $this->addSql('DELETE FROM blog_comment');
        $this->addSql('ALTER TABLE blog_comment CHANGE thread_id thread_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment CHANGE thread_id thread_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_BLOG_COMMENT_THREAD');
        $this->addSql('DROP INDEX IDX_BLOG_COMMENT_THREAD ON blog_comment');
        $this->addSql("INSERT INTO blog_comment (topic_id, author_user_id, author_name, content, moderation_status, moderation_reason, created_at)
            SELECT
                topic_id,
                author_user_id,
                author_name,
                content,
                moderation_status,
                moderation_reason,
                created_at
            FROM blog_thread");
        $this->addSql('ALTER TABLE blog_comment DROP thread_id');

        $this->addSql('ALTER TABLE blog_thread DROP FOREIGN KEY FK_BLOG_THREAD_TOPIC');
        $this->addSql('ALTER TABLE blog_thread DROP FOREIGN KEY FK_BLOG_THREAD_AUTHOR');
        $this->addSql('DROP TABLE blog_thread');
    }
}
