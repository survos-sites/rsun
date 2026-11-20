<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** Local experiment: immutable snapshots, native PDO and BM25 full-text search. */
final readonly class DuckDbBills
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir) {}

    public function build(): array
    {
        if (!extension_loaded('pdo_duckdb')) {
            throw new \RuntimeException('The native pdo_duckdb extension is required.');
        }
        $files = glob($this->projectDir.'/data/[0-9][0-9][0-9][0-9].jsonl');
        if (!$files) {
            throw new \RuntimeException('Download the bill JSONL snapshots first.');
        }
        sort($files);
        $directory = $this->projectDir.'/var/duckdb';
        (new Filesystem())->mkdir($directory);
        $file = 'bills-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.duckdb';
        $started = hrtime(true);
        $db = new \PDO('duckdb:'.$directory.'/'.$file);
        $db->exec("SET memory_limit = '2GB'; SET threads = 1; SET preserve_insertion_order = false; INSTALL fts; LOAD fts");
        $sources = implode(', ', array_map($db->quote(...), $files));
        $db->exec("CREATE TABLE raw_bills AS SELECT json AS record FROM read_json_objects([$sources], format='newline_delimited')");
        $rawCount = (int) $db->query('SELECT count(*) FROM raw_bills')->fetchColumn();
        $conflicts = (int) $db->query("SELECT count(*) FROM (SELECT json_extract_string(record, '$.id') FROM raw_bills GROUP BY 1 HAVING count(DISTINCT md5(record::VARCHAR)) > 1)")->fetchColumn();
        if ($conflicts > 0) {
            throw new \RuntimeException('Conflicting records share a bill ID; reconcile the source before publishing.');
        }
        // Preserve the complete source record, including nested histories and text versions.
        $projection = <<<'SQL'
            SELECT CAST(json_extract_string(record, '$.id') AS BIGINT) AS id,
                   CAST(json_extract_string(record, '$.year') AS INTEGER) AS year,
                   json_extract_string(record, '$.number') AS number,
                   json_extract_string(record, '$.chamber') AS chamber,
                   json_extract_string(record, '$.status') AS status,
                   json_extract_string(record, '$.outcome') AS outcome,
                   json_extract_string(record, '$.patron_party') AS patron_party,
                   json_extract_string(record, '$.committee_shortname') AS committee,
                   json_extract_string(record, '$.catch_line') AS catch_line,
                   json_extract_string(record, '$.patron_name_formatted') AS patron_name,
                   regexp_replace(coalesce(json_extract_string(record, '$.summary'), ''), '<[^>]*>', ' ', 'g') AS summary,
                   regexp_replace(coalesce(json_extract_string(record, '$.full_text'), ''), '<[^>]*>', ' ', 'g') AS full_text,
                   record
            FROM raw_bills
            SQL;
        $db->exec('CREATE TABLE selected_rows AS SELECT min(rowid) AS source_row FROM raw_bills GROUP BY json_extract_string(record, \'$.id\')');
        $db->exec('CREATE TABLE bills AS '.$projection.' WHERE false');
        // Bound JSON parsing memory: large bill texts otherwise multiply across extraction vectors.
        for ($offset = 0; $offset < $rawCount; $offset += 128) {
            $db->exec('INSERT INTO bills '.$projection.' WHERE rowid >= '.$offset.' AND rowid < '.($offset + 128).' AND rowid IN (SELECT source_row FROM selected_rows)');
        }
        $db->exec('ALTER TABLE bills ADD PRIMARY KEY (id)');
        $db->exec(<<<'SQL'
            CREATE TABLE bill_versions AS
            SELECT b.id AS bill_id, CAST(v.key AS INTEGER) AS ordinal,
                   json_extract_string(v.value, '$.number') AS number,
                   json_extract_string(v.value, '$.date') AS date,
                   json_extract_string(v.value, '$.pdf_url') AS pdf_url
            FROM bills b, json_each(b.record, '$.text') v
            SQL);
        $importSeconds = (hrtime(true) - $started) / 1e9;
        $db->exec("PRAGMA create_fts_index('bills', 'id', 'number', 'catch_line', 'summary', 'full_text', 'patron_name', ignore='[^a-z0-9]+')");
        $metadata = [
            'file' => $file, 'builtAt' => date(DATE_ATOM),
            'engine' => $db->query('SELECT version()')->fetchColumn(),
            'sourceRows' => $rawCount,
            'bills' => (int) $db->query('SELECT count(*) FROM bills')->fetchColumn(),
            'versions' => (int) $db->query('SELECT count(*) FROM bill_versions')->fetchColumn(),
            'years' => $db->query('SELECT year, count(*) AS count FROM bills GROUP BY year ORDER BY year')->fetchAll(\PDO::FETCH_ASSOC),
            'importSeconds' => round($importSeconds, 3),
            'buildSeconds' => round((hrtime(true) - $started) / 1e9, 3),
            'sources' => array_map(static fn (string $path): array => ['file' => basename($path), 'sha256' => hash_file('sha256', $path)], $files),
        ];
        $db->exec('DROP TABLE raw_bills; DROP TABLE selected_rows; CHECKPOINT');
        // COPY compacts reclaimed staging/FTS-build blocks, preserving tables and FTS macros.
        $compact = str_replace('.duckdb', '-compact.duckdb', $file);
        $databaseName = str_replace('"', '""', $db->query('SELECT current_database()')->fetchColumn());
        $db->exec('ATTACH '.$db->quote($directory.'/'.$compact).' AS published');
        $db->exec('COPY FROM DATABASE "'.$databaseName.'" TO published');
        $db->exec('DETACH published');
        $db = null;
        (new Filesystem())->remove($directory.'/'.$file);
        $metadata['file'] = $compact;
        $metadata['buildSeconds'] = round((hrtime(true) - $started) / 1e9, 3);
        $metadata['bytes'] = filesize($directory.'/'.$compact);
        // Publish only after the complete database is closed and checkpointed.
        (new Filesystem())->dumpFile($directory.'/current.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $metadata;
    }

    public function metadata(): array
    {
        $path = $this->projectDir.'/var/duckdb/current.json';
        if (!is_file($path)) {
            throw new \RuntimeException('Run php bin/console app:duckdb:build first.');
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function open(): \PDO
    {
        $file = $this->projectDir.'/var/duckdb/'.basename($this->metadata()['file']);
        $db = new \PDO('duckdb:'.$file, options: [\PDO::DUCKDB_ATTR_CONFIG => ['access_mode' => 'read_only', 'threads' => '2']]);
        $db->exec('LOAD fts');

        return $db;
    }

    public function bill(int $id): ?array
    {
        $db = $this->open();
        $stmt = $db->prepare('SELECT record::VARCHAR FROM bills WHERE id = ?');
        $stmt->execute([$id]);
        $record = $stmt->fetchColumn();

        return $record === false ? null : json_decode($record, true, flags: JSON_THROW_ON_ERROR);
    }
}
