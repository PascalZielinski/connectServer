<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261016002426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE direct_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, uuid BINARY(16) NOT NULL, author_id INT NOT NULL, conversation_id INT NOT NULL, INDEX IDX_1416AF939AC03968B8E8428 (conversation_id, created_at), INDEX IDX_1416AF93F675F31B (author_id), INDEX IDX_1416AF939AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dm_conversation (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_FB17DED7D17F50A6 (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dm_participant (id INT AUTO_INCREMENT NOT NULL, hidden TINYINT DEFAULT 0 NOT NULL, conversation_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_FFE2DC559AC0396A76ED395 (conversation_id, user_id), INDEX IDX_FFE2DC559AC0396 (conversation_id), INDEX IDX_FFE2DC55A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE direct_message ADD CONSTRAINT FK_1416AF93F675F31B FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE direct_message ADD CONSTRAINT FK_1416AF939AC0396 FOREIGN KEY (conversation_id) REFERENCES dm_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_participant ADD CONSTRAINT FK_FFE2DC559AC0396 FOREIGN KEY (conversation_id) REFERENCES dm_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dm_participant ADD CONSTRAINT FK_FFE2DC55A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE direct_message DROP FOREIGN KEY FK_1416AF93F675F31B');
        $this->addSql('ALTER TABLE direct_message DROP FOREIGN KEY FK_1416AF939AC0396');
        $this->addSql('ALTER TABLE dm_participant DROP FOREIGN KEY FK_FFE2DC559AC0396');
        $this->addSql('ALTER TABLE dm_participant DROP FOREIGN KEY FK_FFE2DC55A76ED395');
        $this->addSql('DROP TABLE direct_message');
        $this->addSql('DROP TABLE dm_conversation');
        $this->addSql('DROP TABLE dm_participant');
    }
}
