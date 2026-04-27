<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notifications for replies to user comments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, recipient_id INT NOT NULL, actor_user_id INT DEFAULT NULL, actor_name VARCHAR(120) NOT NULL, type VARCHAR(30) NOT NULL, source_type VARCHAR(30) NOT NULL, target_title VARCHAR(180) NOT NULL, target_url VARCHAR(500) NOT NULL, read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BF5476CA28E0F56F (recipient_id), INDEX IDX_BF5476CA81E1820 (actor_user_id), INDEX idx_notification_recipient_read (recipient_id, read_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA28E0F56F FOREIGN KEY (recipient_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA81E1820 FOREIGN KEY (actor_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA28E0F56F');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA81E1820');
        $this->addSql('DROP TABLE notification');
    }
}
