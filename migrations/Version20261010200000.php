<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opaque file uploads with a 24-hour retention period.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stored_uploads (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, uploaded_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, UNIQUE INDEX uniq_stored_uploads_uuid (uuid), INDEX idx_stored_uploads_expires_at (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stored_uploads');
    }
}
