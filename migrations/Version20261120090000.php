<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow current legislative status and committee names without truncation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bill ALTER status TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE bill ALTER status_detail TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE bill ALTER committee_shortname TYPE VARCHAR(64)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Newer bill values exceed the original column lengths.');
    }
}
