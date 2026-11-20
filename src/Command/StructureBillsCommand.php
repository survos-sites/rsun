<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BillTextParser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:bills:structure', 'Persist archive version references and available full-text sections')]
final readonly class StructureBillsCommand
{
    public function __construct(private Connection $db, private BillTextParser $parser) {}

    public function __invoke(SymfonyStyle $io, #[Option('Legislative session year')] int $year = 2026, #[Option('Rebuild derived section text while retaining unchanged identifiers')] bool $reparse = false): int
    {
        $count = 0;
        foreach ($this->db->iterateAssociative('SELECT id, version, text, full_text, url FROM bill WHERE year = ? ORDER BY id', [$year]) as $bill) {
            $this->db->transactional(function () use ($bill, $reparse): void {
                $versions = json_decode($bill['text'] ?? '[]', true, flags: JSON_THROW_ON_ERROR) ?? [];
                $code = strtoupper($bill['version'] ?: 'ARCHIVE-SNAPSHOT');
                $known = array_map(static fn ($v) => strtoupper($v['number']), $versions);
                if (!in_array($code, $known, true)) {
                    $versions[] = ['number' => $code];
                }
                foreach ($versions as $version) {
                    $versionCode = strtoupper($version['number']);
                    $id = hash('sha256', $bill['id'].':'.$versionCode);
                    $this->db->executeStatement('INSERT INTO bill_version (id, bill_id, code, source_date, source_url, source_metadata)
                        VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (id) DO UPDATE SET source_date=EXCLUDED.source_date, source_url=EXCLUDED.source_url, source_metadata=EXCLUDED.source_metadata',
                        [$id, $bill['id'], $versionCode, $version['date'] ?? null, $version['pdf_url'] ?? null,
                            json_encode(['archiveReference' => $version, 'bodyAssociation' => $code === $versionCode ? 'archive-reported-current-version' : 'metadata-only'], JSON_THROW_ON_ERROR)]);
                    if ($code !== $versionCode || !$bill['full_text']) {
                        continue;
                    }
                    $hash = hash('sha256', $bill['full_text']);
                    $previousHash = $this->db->fetchOne('SELECT source_hash FROM bill_version WHERE id=?', [$id]);
                    if (!$reparse && $hash === $previousHash) {
                        continue;
                    }
                    $document = $this->parser->parse($bill['full_text']);
                    $this->db->update('bill_version', ['source_html' => $bill['full_text'], 'source_hash' => $hash], ['id' => $id]);
                    if ($hash !== $previousHash) $this->db->delete('bill_section', ['version_id' => $id]);
                    foreach ($document['sections'] as $position => $section) {
                        $row = [
                            'id' => hash('sha256', $id.':'.$hash.':'.$position), 'version_id' => $id,
                            'position' => $position, 'anchor' => $section['anchor'], 'heading' => $section['heading'],
                            'html' => $section['html'], 'text' => $section['text'], 'proposed_text' => $section['proposedText'],
                            'changes' => json_encode($section['changes'], JSON_THROW_ON_ERROR),
                        ];
                        $exists = $this->db->fetchOne('SELECT 1 FROM bill_section WHERE id=?', [$row['id']]);
                        if ($exists) $this->db->update('bill_section', $row, ['id' => $row['id']]);
                        else $this->db->insert('bill_section', $row);
                    }
                    $this->db->executeStatement('DELETE FROM bill_section WHERE version_id=? AND position>=?', [$id, count($document['sections'])]);
                }
            });
            ++$count;
        }
        $io->success(sprintf('Structured %d bills from %d; missing historical bodies remain metadata-only.', $count, $year));
        return 0;
    }
}
