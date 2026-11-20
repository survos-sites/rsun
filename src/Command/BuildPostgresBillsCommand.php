<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'dev')]
#[AsCommand('app:postgres:build', 'Build PostgreSQL full-text/BM25 search from imported bills')]
final readonly class BuildPostgresBillsCommand
{
    public function __construct(private Connection $connection) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $db = $this->connection;
        $db->executeStatement('CREATE EXTENSION IF NOT EXISTS pg_textsearch');
        $db->transactional(function (Connection $db) use ($io): void {
            $db->executeStatement("CREATE TABLE IF NOT EXISTS rsun_bill_search (
                id BIGINT PRIMARY KEY, year INTEGER, number TEXT, chamber TEXT, status TEXT, outcome TEXT,
                patron_party TEXT, committee TEXT, catch_line TEXT, patron_name TEXT, summary TEXT, search_text TEXT,
                search_vector TSVECTOR GENERATED ALWAYS AS (to_tsvector('english', search_text)) STORED
            )");
            $db->executeStatement('TRUNCATE rsun_bill_search');
            $count = $db->executeStatement("INSERT INTO rsun_bill_search
                (id, year, number, chamber, status, outcome, patron_party, committee, catch_line, patron_name, summary, search_text)
                SELECT id, year, number, chamber, status, outcome, patron_party, committee_shortname,
                    catch_line, patron_name_formatted, summary,
                    regexp_replace(concat_ws(' ', number, catch_line, summary, full_text, patron_name_formatted), '<[^>]*>', ' ', 'g')
                FROM bill");
            $db->executeStatement("CREATE INDEX IF NOT EXISTS rsun_bill_search_bm25 ON rsun_bill_search USING bm25(search_text) WITH (text_config='english')");
            $db->executeStatement('CREATE INDEX IF NOT EXISTS rsun_bill_search_fts ON rsun_bill_search USING gin(search_vector)');
            $io->success(sprintf('Indexed %d bills from PostgreSQL.', $count));
        });
        $db->executeStatement('ANALYZE rsun_bill_search');

        return Command::SUCCESS;
    }
}
