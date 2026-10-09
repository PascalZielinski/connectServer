<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009123155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE channels (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(255) NOT NULL, server_settings_id INT NOT NULL, INDEX IDX_F314E2B611F46153 (server_settings_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE members (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, joined_at DATETIME NOT NULL, server_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_members_server_user (server_id, user_id), INDEX IDX_45A0D2FF1844E6B7 (server_id), INDEX IDX_45A0D2FFA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE profiles (id INT AUTO_INCREMENT NOT NULL, display_name VARCHAR(120) DEFAULT NULL, bio VARCHAR(2000) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8B308530A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE refresh_tokens (refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server_settings (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(2000) DEFAULT NULL, publicy TINYINT NOT NULL, profile_image VARCHAR(2048) DEFAULT NULL, server_id INT NOT NULL, UNIQUE INDEX UNIQ_27E021791844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE servers (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX uniq_servers_uuid (uuid), INDEX IDX_4F8AF5F7B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_users_email (email), UNIQUE INDEX uniq_users_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE channels ADD CONSTRAINT FK_F314E2B611F46153 FOREIGN KEY (server_settings_id) REFERENCES server_settings (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE members ADD CONSTRAINT FK_45A0D2FF1844E6B7 FOREIGN KEY (server_id) REFERENCES servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE members ADD CONSTRAINT FK_45A0D2FFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profiles ADD CONSTRAINT FK_8B308530A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE server_settings ADD CONSTRAINT FK_27E021791844E6B7 FOREIGN KEY (server_id) REFERENCES servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE servers ADD CONSTRAINT FK_4F8AF5F7B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE channels DROP FOREIGN KEY FK_F314E2B611F46153');
        $this->addSql('ALTER TABLE members DROP FOREIGN KEY FK_45A0D2FF1844E6B7');
        $this->addSql('ALTER TABLE members DROP FOREIGN KEY FK_45A0D2FFA76ED395');
        $this->addSql('ALTER TABLE profiles DROP FOREIGN KEY FK_8B308530A76ED395');
        $this->addSql('ALTER TABLE server_settings DROP FOREIGN KEY FK_27E021791844E6B7');
        $this->addSql('ALTER TABLE servers DROP FOREIGN KEY FK_4F8AF5F7B03A8386');
        $this->addSql('DROP TABLE channels');
        $this->addSql('DROP TABLE members');
        $this->addSql('DROP TABLE profiles');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE server_settings');
        $this->addSql('DROP TABLE servers');
        $this->addSql('DROP TABLE users');
    }
}
