<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326143954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dataset_info (dataset_key VARCHAR(128) NOT NULL, label VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, aggregator VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, country VARCHAR(255) DEFAULT NULL, contact_url VARCHAR(255) DEFAULT NULL, rights_uri VARCHAR(255) DEFAULT NULL, obj_count INT NOT NULL, meta_path VARCHAR(255) DEFAULT NULL, raw_path VARCHAR(255) DEFAULT NULL, normalized_path VARCHAR(255) DEFAULT NULL, profile_path VARCHAR(255) DEFAULT NULL, pixie_db_path VARCHAR(255) DEFAULT NULL, status VARCHAR(32) NOT NULL, normalized_count INT DEFAULT NULL, pixie_row_count INT DEFAULT NULL, meili_doc_count INT DEFAULT NULL, last_scanned TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_normalized TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_indexed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cores JSON NOT NULL, fields JSONB NOT NULL, meili_settings JSONB NOT NULL, meta JSONB NOT NULL, PRIMARY KEY (dataset_key))');
        $this->addSql('CREATE INDEX IDX_4E0D6452BB5381D3 ON dataset_info (aggregator)');
        $this->addSql('CREATE INDEX IDX_4E0D64524180C698 ON dataset_info (locale)');
        $this->addSql('CREATE INDEX IDX_4E0D64527B00651C ON dataset_info (status)');
        $this->addSql('ALTER TABLE index_info ADD label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE index_info ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE index_info ADD aggregator VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE index_info ADD institution VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE index_info ADD country VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE dataset_info');
        $this->addSql('ALTER TABLE index_info DROP label');
        $this->addSql('ALTER TABLE index_info DROP description');
        $this->addSql('ALTER TABLE index_info DROP aggregator');
        $this->addSql('ALTER TABLE index_info DROP institution');
        $this->addSql('ALTER TABLE index_info DROP country');
    }
}
