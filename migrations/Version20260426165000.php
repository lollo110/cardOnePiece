<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add community decks and deck card entries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE deck (id INT AUTO_INCREMENT NOT NULL, owner_id INT DEFAULT NULL, title VARCHAR(160) NOT NULL, slug VARCHAR(190) DEFAULT NULL, archetype VARCHAR(120) DEFAULT NULL, description LONGTEXT DEFAULT NULL, is_public TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_4FAC36377E3C61F9 (owner_id), INDEX idx_deck_public_updated (is_public, updated_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE deck_card (id INT AUTO_INCREMENT NOT NULL, deck_id INT NOT NULL, card_id INT NOT NULL, quantity INT NOT NULL, section VARCHAR(40) NOT NULL, position INT NOT NULL, INDEX IDX_2AF3DCED111948DC (deck_id), INDEX IDX_2AF3DCED4ACC9A20 (card_id), INDEX idx_deck_card_section (section), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC36397E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE deck_card ADD CONSTRAINT FK_FDA7B98311C45DDC FOREIGN KEY (deck_id) REFERENCES deck (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deck_card ADD CONSTRAINT FK_FDA7B9834ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deck_card DROP FOREIGN KEY FK_FDA7B98311C45DDC');
        $this->addSql('ALTER TABLE deck_card DROP FOREIGN KEY FK_FDA7B9834ACC9A20');
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC36397E3C61F9');
        $this->addSql('DROP TABLE deck_card');
        $this->addSql('DROP TABLE deck');
    }
}
