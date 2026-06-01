<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FULLTEXT index on articles(title, summary, content) for full-text search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles ADD FULLTEXT INDEX idx_ft_article_search (title, summary, content)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles DROP INDEX idx_ft_article_search');
    }
}
