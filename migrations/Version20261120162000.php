<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261120162000 extends AbstractMigration
{
    public function getDescription(): string { return 'BM25 index for bill section chunks'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_textsearch');
        $this->addSql("CREATE INDEX rsun_ai_chunk_bm25 ON rsun_ai_chunk USING bm25(text) WITH(text_config='english')");
    }
    public function down(Schema $schema): void { $this->addSql('DROP INDEX rsun_ai_chunk_bm25'); }
}
