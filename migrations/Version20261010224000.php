<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010224000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove temporary defaults from stored upload metadata columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stored_uploads CHANGE original_filename original_filename VARCHAR(255) NOT NULL, CHANGE media_type media_type VARCHAR(127) NOT NULL, CHANGE image image TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE stored_uploads CHANGE original_filename original_filename VARCHAR(255) NOT NULL DEFAULT 'download.bin', CHANGE media_type media_type VARCHAR(127) NOT NULL DEFAULT 'application/octet-stream', CHANGE image image TINYINT(1) NOT NULL DEFAULT 0");
    }
}
