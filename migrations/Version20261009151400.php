<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009151400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add UUIDs to channels and persistent text-channel messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channels ADD uuid VARCHAR(36) DEFAULT NULL');
        $this->addSql('UPDATE channels SET uuid = UUID() WHERE uuid IS NULL');
        $this->addSql('ALTER TABLE channels MODIFY uuid VARCHAR(36) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_channels_uuid ON channels (uuid)');

        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, channel_id INT NOT NULL, author_id INT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX idx_messages_channel_created (channel_id, created_at, id), UNIQUE INDEX uniq_messages_uuid (uuid), INDEX idx_messages_author (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_MESSAGES_CHANNEL FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_MESSAGES_AUTHOR FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_MESSAGES_CHANNEL');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_MESSAGES_AUTHOR');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP INDEX uniq_channels_uuid ON channels');
        $this->addSql('ALTER TABLE channels DROP uuid');
    }
}
