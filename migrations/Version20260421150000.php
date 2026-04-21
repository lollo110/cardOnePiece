<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add discussion type and language filters to card comments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE card_comment ADD discussion_type VARCHAR(20) NOT NULL DEFAULT 'trading', ADD language VARCHAR(40) NOT NULL DEFAULT 'english'");
        $this->addSql('CREATE INDEX IDX_CARD_COMMENT_DISCUSSION ON card_comment (card_id, discussion_type, language, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_CARD_COMMENT_DISCUSSION ON card_comment');
        $this->addSql('ALTER TABLE card_comment DROP discussion_type, DROP language');
    }
}
