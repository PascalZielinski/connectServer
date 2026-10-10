<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010223902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store safe uploaded filenames and image metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE stored_uploads ADD original_filename VARCHAR(255) NOT NULL DEFAULT 'download.bin', ADD media_type VARCHAR(127) NOT NULL DEFAULT 'application/octet-stream', ADD image TINYINT(1) NOT NULL DEFAULT 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stored_uploads DROP original_filename, DROP media_type, DROP image');
    }
}
