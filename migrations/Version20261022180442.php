<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022180442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trusted_device ADD device_secret_hash VARCHAR(64) NOT NULL, DROP ip_address');
        $this->addSql('CREATE UNIQUE INDEX uniq_device_user_secret ON trusted_device (user_id, device_secret_hash)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_device_user_secret ON trusted_device');
        $this->addSql('ALTER TABLE trusted_device ADD ip_address VARCHAR(45) NOT NULL, DROP device_secret_hash');
    }
}
