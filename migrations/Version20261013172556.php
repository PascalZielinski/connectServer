<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013172556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE server_image (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, uuid BINARY(16) NOT NULL, server_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_65BC66561844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE server_image ADD CONSTRAINT FK_65BC66561844E6B7 FOREIGN KEY (server_id) REFERENCES server_settings (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE server_image DROP FOREIGN KEY FK_65BC66561844E6B7');
        $this->addSql('DROP TABLE server_image');
    }
}
