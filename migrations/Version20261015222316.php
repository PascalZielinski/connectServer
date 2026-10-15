<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261015222316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE channels (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(255) NOT NULL, server_settings_id INT NOT NULL, UNIQUE INDEX uniq_channels_uuid (uuid), INDEX IDX_F314E2B611F46153 (server_settings_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE members (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, joined_at DATETIME NOT NULL, server_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_members_server_user (server_id, user_id), INDEX IDX_45A0D2FF1844E6B7 (server_id), INDEX IDX_45A0D2FFA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, content LONGTEXT DEFAULT NULL, attachment_uuid VARCHAR(36) DEFAULT NULL, created_at DATETIME NOT NULL, channel_id INT NOT NULL, author_id INT NOT NULL, UNIQUE INDEX UNIQ_DB021E96290C4499 (attachment_uuid), INDEX idx_messages_channel_created (channel_id, created_at, id), INDEX idx_messages_author (author_id), UNIQUE INDEX uniq_messages_uuid (uuid), INDEX IDX_DB021E9672F5A1AA (channel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE profile_image (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, uuid VARCHAR(255) NOT NULL, profile_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_32E99B8DCCFA12B8 (profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE profiles (id INT AUTO_INCREMENT NOT NULL, display_name VARCHAR(120) DEFAULT NULL, bio VARCHAR(2000) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8B308530D5499347 (display_name), UNIQUE INDEX UNIQ_8B308530A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE refresh_tokens (refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server_image (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, uuid BINARY(16) NOT NULL, server_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_65BC66561844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server_settings (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(2000) DEFAULT NULL, publicy TINYINT NOT NULL, profile_image VARCHAR(2048) DEFAULT NULL, server_id INT NOT NULL, UNIQUE INDEX UNIQ_27E021791844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE servers (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX uniq_servers_uuid (uuid), INDEX IDX_4F8AF5F7B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stored_uploads (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, uploaded_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, original_filename VARCHAR(255) NOT NULL, media_type VARCHAR(127) NOT NULL, image TINYINT NOT NULL, INDEX idx_stored_uploads_expires_at (expires_at), UNIQUE INDEX uniq_stored_uploads_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_public_key (id INT AUTO_INCREMENT NOT NULL, public_key VARCHAR(64) DEFAULT NULL, device_name VARCHAR(255) DEFAULT NULL, verified_at DATETIME DEFAULT NULL, last_seen_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_C19E128FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, roles JSON NOT NULL, UNIQUE INDEX uniq_users_email (email), UNIQUE INDEX uniq_users_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE channels ADD CONSTRAINT FK_F314E2B611F46153 FOREIGN KEY (server_settings_id) REFERENCES server_settings (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE members ADD CONSTRAINT FK_45A0D2FF1844E6B7 FOREIGN KEY (server_id) REFERENCES servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE members ADD CONSTRAINT FK_45A0D2FFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E9672F5A1AA FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile_image ADD CONSTRAINT FK_32E99B8DCCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id)');
        $this->addSql('ALTER TABLE profiles ADD CONSTRAINT FK_8B308530A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_image ADD CONSTRAINT FK_65BC66561844E6B7 FOREIGN KEY (server_id) REFERENCES server_settings (id)');
        $this->addSql('ALTER TABLE server_settings ADD CONSTRAINT FK_27E021791844E6B7 FOREIGN KEY (server_id) REFERENCES servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE servers ADD CONSTRAINT FK_4F8AF5F7B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_public_key ADD CONSTRAINT FK_C19E128FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE channels DROP FOREIGN KEY FK_F314E2B611F46153');
        $this->addSql('ALTER TABLE members DROP FOREIGN KEY FK_45A0D2FF1844E6B7');
        $this->addSql('ALTER TABLE members DROP FOREIGN KEY FK_45A0D2FFA76ED395');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E9672F5A1AA');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96F675F31B');
        $this->addSql('ALTER TABLE profile_image DROP FOREIGN KEY FK_32E99B8DCCFA12B8');
        $this->addSql('ALTER TABLE profiles DROP FOREIGN KEY FK_8B308530A76ED395');
        $this->addSql('ALTER TABLE server_image DROP FOREIGN KEY FK_65BC66561844E6B7');
        $this->addSql('ALTER TABLE server_settings DROP FOREIGN KEY FK_27E021791844E6B7');
        $this->addSql('ALTER TABLE servers DROP FOREIGN KEY FK_4F8AF5F7B03A8386');
        $this->addSql('ALTER TABLE user_public_key DROP FOREIGN KEY FK_C19E128FA76ED395');
        $this->addSql('DROP TABLE channels');
        $this->addSql('DROP TABLE members');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE profile_image');
        $this->addSql('DROP TABLE profiles');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE server_image');
        $this->addSql('DROP TABLE server_settings');
        $this->addSql('DROP TABLE servers');
        $this->addSql('DROP TABLE stored_uploads');
        $this->addSql('DROP TABLE user_public_key');
        $this->addSql('DROP TABLE users');
    }
}
