<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421210500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align parent blog comment index name with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment RENAME INDEX IDX_7882EFEF297B7AE0 TO IDX_7882EFEFBF2AF943');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_comment RENAME INDEX IDX_7882EFEFBF2AF943 TO IDX_7882EFEF297B7AE0');
    }
}
