<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020142612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_public_key DROP FOREIGN KEY `FK_C19E128FA76ED395`');
        $this->addSql('DROP TABLE user_public_key');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_public_key (id INT AUTO_INCREMENT NOT NULL, public_key VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, device_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, verified_at DATETIME DEFAULT NULL, last_seen_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_C19E128FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_public_key ADD CONSTRAINT `FK_C19E128FA76ED395` FOREIGN KEY (user_id) REFERENCES users (id)');
    }
}
