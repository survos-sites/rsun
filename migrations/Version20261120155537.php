<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120155537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store bill versions, source availability, sections and inline changes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bill_section (id VARCHAR(64) NOT NULL, position INT NOT NULL, anchor VARCHAR(64) NOT NULL, heading TEXT NOT NULL, html TEXT NOT NULL, text TEXT NOT NULL, proposed_text TEXT NOT NULL, changes JSON NOT NULL, version_id VARCHAR(64) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX bill_section_position ON bill_section (version_id, position)');
        $this->addSql('CREATE INDEX IDX_D9B04EF04BBC2705 ON bill_section (version_id)');
        $this->addSql('CREATE TABLE bill_version (id VARCHAR(64) NOT NULL, code VARCHAR(128) NOT NULL, source_date VARCHAR(10) DEFAULT NULL, source_url TEXT DEFAULT NULL, source_html TEXT DEFAULT NULL, source_hash VARCHAR(64) DEFAULT NULL, source_metadata JSON NOT NULL, bill_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX bill_version_identity ON bill_version (bill_id, code)');
        $this->addSql('CREATE INDEX IDX_4BDFE7DC1A8C12F5 ON bill_version (bill_id)');
        $this->addSql('ALTER TABLE bill_section ADD CONSTRAINT FK_D9B04EF04BBC2705 FOREIGN KEY (version_id) REFERENCES bill_version (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE bill_version ADD CONSTRAINT FK_4BDFE7DC1A8C12F5 FOREIGN KEY (bill_id) REFERENCES bill (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bill_section DROP CONSTRAINT FK_D9B04EF04BBC2705');
        $this->addSql('ALTER TABLE bill_version DROP CONSTRAINT FK_4BDFE7DC1A8C12F5');
        $this->addSql('DROP TABLE bill_section');
        $this->addSql('DROP TABLE bill_version');
    }
}
