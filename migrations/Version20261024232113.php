<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261024232113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trusted_device DROP FOREIGN KEY `FK_F37E8F7BA76ED395`');
        $this->addSql('ALTER TABLE trusted_device ADD CONSTRAINT FK_F37E8F7BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trusted_device DROP FOREIGN KEY FK_F37E8F7BA76ED395');
        $this->addSql('ALTER TABLE trusted_device ADD CONSTRAINT `FK_F37E8F7BA76ED395` FOREIGN KEY (user_id) REFERENCES users (id)');
    }
}
