<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261120161000 extends AbstractMigration
{
    public function getDescription(): string { return 'Content-addressed Gemini embeddings and section chunks for PostgreSQL and Elasticsearch'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql("CREATE TABLE rsun_ai_embedding (id VARCHAR(64) PRIMARY KEY, model TEXT NOT NULL, text TEXT NOT NULL, tokens INT, status TEXT NOT NULL DEFAULT 'new', embedding vector(1024), error TEXT, updated_at TIMESTAMPTZ NOT NULL DEFAULT now())");
        $this->addSql("CREATE TABLE rsun_ai_chunk (id VARCHAR(64) PRIMARY KEY, section_id VARCHAR(64) NOT NULL REFERENCES bill_section(id) ON DELETE CASCADE, position INT NOT NULL, embedding_id VARCHAR(64) NOT NULL REFERENCES rsun_ai_embedding(id), text TEXT NOT NULL, search_vector TSVECTOR GENERATED ALWAYS AS (to_tsvector('english', text)) STORED, UNIQUE(section_id, position))");
        $this->addSql('CREATE INDEX rsun_ai_chunk_fts ON rsun_ai_chunk USING gin(search_vector)');
        $this->addSql('CREATE INDEX rsun_ai_chunk_section ON rsun_ai_chunk(section_id)');
        $this->addSql('CREATE INDEX rsun_ai_chunk_embedding ON rsun_ai_chunk(embedding_id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rsun_ai_chunk');
        $this->addSql('DROP TABLE rsun_ai_embedding');
    }
}
