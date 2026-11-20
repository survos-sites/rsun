<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class BillRetrieval
{
    public function __construct(private Connection $db, private \Symfony\Component\HttpFoundation\RequestStack $requests, private BillEmbeddings $embeddings) {}

    public function find(string $query, int $year = 2026, int $billId = 0): array
    {
        [$year, $billId] = $this->scope($year, $billId);
        if (mb_strlen($query) > 500) {
            throw new \InvalidArgumentException('Search query exceeds 500 characters.');
        }
        if ('' === trim($query)) return [];
        $fields = "s.id, b.id AS bill_id, b.number, b.year, b.catch_line AS title, b.status, b.outcome,
            v.code AS version, v.source_date, s.heading, c.text AS excerpt,
            '/bills/sections/' || s.id AS citation";
        $joins = ' FROM rsun_ai_chunk c JOIN bill_section s ON s.id=c.section_id
            JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id ';
        $lexical = $this->db->fetchAllAssociative('SELECT '.$fields.$joins.
            " WHERE b.year=? AND (?=0 OR b.id=?) AND c.search_vector @@ websearch_to_tsquery('english', ?)
            ORDER BY c.text <@> to_bm25query(?, 'rsun_ai_chunk_bm25'), c.id LIMIT 50",
            [$year,$billId,$billId,$query,$query]);
        $vector = $this->embeddings->query('task: search result | query: '.$query);
        $semantic = $this->db->fetchAllAssociative('SELECT '.$fields.$joins.
            " JOIN rsun_ai_embedding e ON e.id=c.embedding_id
            WHERE b.year=? AND (?=0 OR b.id=?) AND e.status='complete' AND e.model=?
            ORDER BY e.embedding <=> ?::vector, c.id LIMIT 50", [$year,$billId,$billId,BillEmbeddings::MODEL,$vector]);
        return self::fuse($lexical, $semantic);
    }

    /** Rank fusion counts a section once per retrieval method, not once per chunk. */
    public static function fuse(array ...$lists): array
    {
        $hits = []; $scores = [];
        foreach ($lists as $list) {
            $seen = []; $rank = 0;
            foreach ($list as $hit) {
                $id = $hit['id'];
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                $hits[$id] ??= $hit;
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (60 + ++$rank);
            }
        }
        arsort($scores);
        return array_map(static fn ($id) => $hits[$id], array_slice(array_keys($scores), 0, 10));
    }

    public function section(string $id): array
    {
        [$year, $billId] = $this->scope(0, 0);
        return $this->db->fetchAssociative("SELECT s.*, b.id AS bill_id, b.number, b.year, b.status, b.outcome,
            v.code AS version, v.source_date, v.source_url, '/bills/sections/' || s.id AS citation
            FROM bill_section s JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id WHERE s.id=? AND (?=0 OR b.year=?) AND (?=0 OR b.id=?)", [$id, $year, $year, $billId, $billId]) ?: [];
    }
    public function bill(int $id): array
    {
        [$year, $scopeId] = $this->scope(0, 0);
        $bill = $this->db->fetchAssociative("SELECT id,year,number,catch_line AS title,summary,patron_name_formatted AS patron,
            status,outcome,version,text AS version_references,full_text FROM bill
            WHERE id=? AND (?=0 OR year=?) AND (?=0 OR id=?)", [$id,$year,$year,$scopeId,$scopeId]);
        if (!$bill) return [];
        $bill['citation'] = '/bills/'.$id;
        $bill['sections'] = $this->db->fetchAllAssociative('SELECT s.id,s.heading,v.code AS version FROM bill_section s JOIN bill_version v ON v.id=s.version_id WHERE v.bill_id=? ORDER BY v.source_date,s.position', [$id]);
        $bill['version_references'] = json_decode($bill['version_references'] ?? '[]', true);
        $bill['full_text_truncated'] = strlen($bill['full_text'] ?? '') > 100000;
        $bill['full_text'] = mb_strcut($bill['full_text'] ?? '', 0, 100000, 'UTF-8');
        return $bill;
    }

    public function summaries(string $query, int $year = 2026, int $billId = 0): array
    {
        [$year, $billId] = $this->scope($year, $billId);
        if (mb_strlen($query) > 500) throw new \InvalidArgumentException('Query exceeds 500 characters');
        return $this->db->fetchAllAssociative("SELECT id,year,number,catch_line AS title,summary,
            patron_name_formatted AS patron,status,outcome,version,'/bills/' || id AS citation
            FROM bill WHERE year=? AND (?=0 OR id=?)
            AND to_tsvector('english',concat_ws(' ',number,catch_line,summary)) @@ websearch_to_tsquery('english',?)
            ORDER BY ts_rank_cd(to_tsvector('english',concat_ws(' ',number,catch_line,summary)),websearch_to_tsquery('english',?)) DESC,id LIMIT 20",
            [$year,$billId,$billId,$query,$query]);
    }

    private function scope(int $year, int $billId): array
    {
        $request = $this->requests->getCurrentRequest();
        if ($request && 'app_bill_chat' === $request->attributes->get('_route')) {
            return [$request->query->getInt('year', 2026), max(0, $request->query->getInt('bill', 0))];
        }
        return [$year, $billId];
    }
}
