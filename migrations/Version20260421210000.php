<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow blog replies to target another reply.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment ADD parent_comment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_7882EFEF297B7AE0 FOREIGN KEY (parent_comment_id) REFERENCES blog_comment (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_7882EFEF297B7AE0 ON blog_comment (parent_comment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEF297B7AE0');
        $this->addSql('DROP INDEX IDX_7882EFEF297B7AE0 ON blog_comment');
        $this->addSql('ALTER TABLE blog_comment DROP parent_comment_id');
    }
}
