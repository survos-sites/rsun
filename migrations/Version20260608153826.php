<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608153826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidate DROP CONSTRAINT fk_c8b28e44fa460441');
        $this->addSql('ALTER TABLE dataset_info DROP CONSTRAINT fk_4e0d6452929d53e4');
        $this->addSql('DROP TABLE candidate');
        $this->addSql('DROP TABLE dataset_info');
        $this->addSql('DROP TABLE provider');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA toolkit_experimental');
        $this->addSql('CREATE SCHEMA timescaledb_information');
        $this->addSql('CREATE SCHEMA timescaledb_experimental');
        $this->addSql('CREATE SCHEMA _timescaledb_internal');
        $this->addSql('CREATE SCHEMA _timescaledb_functions');
        $this->addSql('CREATE SCHEMA _timescaledb_config');
        $this->addSql('CREATE SCHEMA _timescaledb_catalog');
        $this->addSql('CREATE SCHEMA _timescaledb_cache');
        $this->addSql('CREATE TABLE candidate (candidate_key VARCHAR(160) NOT NULL, provider_code VARCHAR(32) NOT NULL, source_id VARCHAR(160) DEFAULT NULL, kind VARCHAR(32) DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, source_url VARCHAR(500) DEFAULT NULL, locale VARCHAR(16) DEFAULT NULL, country VARCHAR(8) DEFAULT NULL, dataset_key VARCHAR(160) DEFAULT NULL, status VARCHAR(32) NOT NULL, meta JSONB NOT NULL, discovered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, hydrated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, promoted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, provider_entity_code VARCHAR(32) DEFAULT NULL, PRIMARY KEY (candidate_key))');
        $this->addSql('CREATE INDEX idx_c8b28e44929d53e4 ON candidate (provider_code)');
        $this->addSql('CREATE INDEX idx_c8b28e44fa460441 ON candidate (provider_entity_code)');
        $this->addSql('CREATE INDEX idx_c8b28e447b00651c ON candidate (status)');
        $this->addSql('CREATE TABLE dataset_info (dataset_key VARCHAR(128) NOT NULL, label VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, aggregator VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, country VARCHAR(255) DEFAULT NULL, contact_url VARCHAR(255) DEFAULT NULL, rights_uri VARCHAR(255) DEFAULT NULL, obj_count INT NOT NULL, meta_path VARCHAR(255) DEFAULT NULL, raw_path VARCHAR(255) DEFAULT NULL, normalized_path VARCHAR(255) DEFAULT NULL, profile_path VARCHAR(255) DEFAULT NULL, pixie_db_path VARCHAR(255) DEFAULT NULL, status VARCHAR(32) NOT NULL, normalized_count INT DEFAULT NULL, pixie_row_count INT DEFAULT NULL, meili_doc_count INT DEFAULT NULL, last_scanned TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_normalized TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_indexed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cores JSON NOT NULL, fields JSONB NOT NULL, profile_summary JSONB DEFAULT NULL, meili_settings JSONB NOT NULL, meta JSONB NOT NULL, provider_code VARCHAR(32) DEFAULT NULL, PRIMARY KEY (dataset_key))');
        $this->addSql('CREATE INDEX idx_4e0d64527b00651c ON dataset_info (status)');
        $this->addSql('CREATE INDEX idx_4e0d6452929d53e4 ON dataset_info (provider_code)');
        $this->addSql('CREATE INDEX idx_4e0d6452bb5381d3 ON dataset_info (aggregator)');
        $this->addSql('CREATE INDEX idx_4e0d64524180c698 ON dataset_info (locale)');
        $this->addSql('CREATE TABLE provider (code VARCHAR(32) NOT NULL, label VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, homepage VARCHAR(500) DEFAULT NULL, logo VARCHAR(500) DEFAULT NULL, approx_inst_count INT DEFAULT NULL, approx_obj_count INT DEFAULT NULL, default_locale VARCHAR(10) DEFAULT NULL, data_reuse VARCHAR(255) DEFAULT NULL, terms_url VARCHAR(255) DEFAULT NULL, dataset_count INT DEFAULT NULL, synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (code))');
        $this->addSql('ALTER TABLE candidate ADD CONSTRAINT fk_c8b28e44fa460441 FOREIGN KEY (provider_entity_code) REFERENCES provider (code) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE dataset_info ADD CONSTRAINT fk_4e0d6452929d53e4 FOREIGN KEY (provider_code) REFERENCES provider (code) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
