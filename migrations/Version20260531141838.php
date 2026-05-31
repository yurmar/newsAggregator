<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531141838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE verification_codes (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(6) NOT NULL, status VARCHAR(10) NOT NULL, attempt_count INT NOT NULL, created_at DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_7B56601DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE verification_codes ADD CONSTRAINT FK_7B56601DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD is_verified TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE verification_codes DROP FOREIGN KEY FK_7B56601DA76ED395');
        $this->addSql('DROP TABLE verification_codes');
        $this->addSql('ALTER TABLE users DROP is_verified');
    }
}
