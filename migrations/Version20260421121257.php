<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421121257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add application users plus optional ownership and moderation metadata for blog and card comments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) DEFAULT NULL, username VARCHAR(60) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, google_id VARCHAR(190) DEFAULT NULL, github_id VARCHAR(190) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), UNIQUE INDEX UNIQ_88BDF3E9F85E0677 (username), UNIQUE INDEX UNIQ_88BDF3E976F5C865 (google_id), UNIQUE INDEX UNIQ_88BDF3E9D4327649 (github_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("ALTER TABLE blog_comment ADD moderation_status VARCHAR(20) NOT NULL DEFAULT 'published', ADD moderation_reason VARCHAR(255) DEFAULT NULL, ADD author_user_id INT DEFAULT NULL");
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEFE2544CD6 FOREIGN KEY (author_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7882EFEFE2544CD6 ON blog_comment (author_user_id)');
        $this->addSql("ALTER TABLE card_comment ADD moderation_status VARCHAR(20) NOT NULL DEFAULT 'published', ADD moderation_reason VARCHAR(255) DEFAULT NULL, ADD author_user_id INT DEFAULT NULL");
        $this->addSql('ALTER TABLE card_comment ADD CONSTRAINT FK_C36ED81AE2544CD6 FOREIGN KEY (author_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C36ED81AE2544CD6 ON card_comment (author_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEFE2544CD6');
        $this->addSql('DROP INDEX IDX_7882EFEFE2544CD6 ON blog_comment');
        $this->addSql('ALTER TABLE card_comment DROP FOREIGN KEY FK_C36ED81AE2544CD6');
        $this->addSql('DROP INDEX IDX_C36ED81AE2544CD6 ON card_comment');
        $this->addSql('ALTER TABLE blog_comment DROP moderation_status, DROP moderation_reason, DROP author_user_id');
        $this->addSql('ALTER TABLE card_comment DROP moderation_status, DROP moderation_reason, DROP author_user_id');
        $this->addSql('DROP TABLE app_user');
    }
}
