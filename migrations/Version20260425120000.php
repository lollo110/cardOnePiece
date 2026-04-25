<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow card comments to answer another card comment.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_comment ADD parent_comment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card_comment ADD CONSTRAINT FK_C36ED81ABF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES card_comment (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_C36ED81ABF2AF943 ON card_comment (parent_comment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_comment DROP FOREIGN KEY FK_C36ED81ABF2AF943');
        $this->addSql('DROP INDEX IDX_C36ED81ABF2AF943 ON card_comment');
        $this->addSql('ALTER TABLE card_comment DROP parent_comment_id');
    }
}
