<?php

declare(strict_types=1);
namespace App\Command;
use App\Service\{BillChunker, BillEmbeddings, BillDiscoveryText};
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\{AsCommand, Option};
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:bills:vectorize', 'Prepare and embed versioned bill section chunks, resuming completed work')]
final readonly class VectorizeBillsCommand
{
    public function __construct(private Connection $db, private BillChunker $chunker, private BillEmbeddings $embeddings, private BillDiscoveryText $discovery) {}
    public function __invoke(SymfonyStyle $io,
        #[Option('Session year')] int $year = 2026,
        #[Option('Only prepare chunks without provider calls')] bool $prepareOnly = false,
        #[Option('Maximum new embeddings; 0 processes all')] int $limit = 0): int
    {
        foreach ($this->db->iterateAssociative('SELECT s.id,s.heading,s.html,s.proposed_text,b.number,b.catch_line,v.code FROM bill_section s JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id WHERE b.year=?', [$year]) as $s) {
            $prefix = $s['number'].' · '.$s['code'].' · '.$s['catch_line']."\n".$s['heading']."\n\n";
            $prefix = mb_strcut($prefix, 0, 1500, 'UTF-8');
            $chunks = $this->chunker->split($this->discovery->fromSection($s['html'], $s['heading'], $s['proposed_text']), 4500);
            $chunks = array_values(array_filter($chunks, static fn ($body) => '' !== trim($body)));
            foreach ($chunks as $position => $body) {
                $text = $prefix.$body;
                $embeddingId = $this->embeddings->register($text);
                $id = hash('sha256', $s['id'].':'.$position.':'.$embeddingId);
                $this->db->executeStatement('INSERT INTO rsun_ai_chunk(id,section_id,position,embedding_id,text) VALUES(?,?,?,?,?) ON CONFLICT(section_id,position) DO UPDATE SET id=EXCLUDED.id,embedding_id=EXCLUDED.embedding_id,text=EXCLUDED.text', [$id,$s['id'],$position,$embeddingId,$text]);
            }
            // A revised section may now have fewer chunks; do not retain obsolete evidence.
            $this->db->executeStatement('DELETE FROM rsun_ai_chunk WHERE section_id=? AND position>=?', [$s['id'], count($chunks)]);
        }
        $pending = $this->db->fetchFirstColumn("SELECT DISTINCT e.id FROM rsun_ai_embedding e JOIN rsun_ai_chunk c ON c.embedding_id=e.id JOIN bill_section s ON s.id=c.section_id JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id WHERE b.year=? AND e.status='new' ORDER BY e.id", [$year]);
        $io->note(count($pending).' new embeddings prepared.');
        if ($prepareOnly) return 0;
        if ($limit > 0) $pending = array_slice($pending, 0, $limit);
        $done = 0;
        foreach (array_chunk($pending, 24) as $ids) {
            $this->embeddings->embed($ids);
            $done += count($ids);
            $io->writeln(sprintf('%d / %d embeddings saved', $done, count($pending)));
            usleep(500000);
        }
        $io->success('Embedding pass complete.');
        return 0;
    }
}
