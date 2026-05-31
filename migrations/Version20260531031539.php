<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531031539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE articles (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(500) NOT NULL, content LONGTEXT DEFAULT NULL, summary LONGTEXT DEFAULT NULL, external_url VARCHAR(600) NOT NULL, image_url VARCHAR(600) DEFAULT NULL, published_at DATETIME NOT NULL, created_at DATETIME NOT NULL, source_id INT DEFAULT NULL, category_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BFDD3168545CEB66 (external_url), INDEX IDX_BFDD3168953C1C61 (source_id), INDEX IDX_BFDD316812469DE2 (category_id), INDEX idx_published_at (published_at), INDEX idx_external_url (external_url), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, UNIQUE INDEX UNIQ_3AF34668989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE news_sources (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, url VARCHAR(400) NOT NULL, type VARCHAR(20) NOT NULL, is_active TINYINT(1) NOT NULL, last_fetched_at DATETIME DEFAULT NULL, default_category_id INT DEFAULT NULL, INDEX IDX_85B97914C6B58E54 (default_category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, type VARCHAR(50) NOT NULL, is_read TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_6000B0D3A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168953C1C61 FOREIGN KEY (source_id) REFERENCES news_sources (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD316812469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE news_sources ADD CONSTRAINT FK_85B97914C6B58E54 FOREIGN KEY (default_category_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles DROP FOREIGN KEY FK_BFDD3168953C1C61');
        $this->addSql('ALTER TABLE articles DROP FOREIGN KEY FK_BFDD316812469DE2');
        $this->addSql('ALTER TABLE news_sources DROP FOREIGN KEY FK_85B97914C6B58E54');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('DROP TABLE articles');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE news_sources');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
