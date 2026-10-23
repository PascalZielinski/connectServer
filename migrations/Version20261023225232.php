<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023225232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trusted_device ADD confirmation_token_expires_at DATETIME DEFAULT NULL, ADD expires_at DATETIME DEFAULT NULL, DROP expired_at');
        $this->addSql('CREATE UNIQUE INDEX uniq_device_confirmation_token ON trusted_device (confirmation_token_hash)');
        $this->addSql('ALTER TABLE users ADD is_verified TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_device_confirmation_token ON trusted_device');
        $this->addSql('ALTER TABLE trusted_device ADD expired_at DATETIME NOT NULL, DROP confirmation_token_expires_at, DROP expires_at');
        $this->addSql('ALTER TABLE users DROP is_verified');
    }
}
