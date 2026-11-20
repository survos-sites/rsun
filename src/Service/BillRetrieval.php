<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class BillRetrieval
{
    public function __construct(private Connection $db, private \Symfony\Component\HttpFoundation\RequestStack $requests, private BillEmbeddings $embeddings) {}

    public function find(string $query, int $year = 2026, int $billId = 0, int $limit = 10, int $offset = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null): array
    {
        [$year, $billId] = $this->scope($year, $billId);
        self::validate($query, $limit, $offset);
        if ('' === trim($query)) return [];
        [$where, $params] = self::filters($year, $billId, $outcome, $status, $chamber, $patron);
        $fields = "s.id, b.id AS bill_id, b.number, b.year, b.catch_line AS title, b.status, b.outcome,
            b.chamber, b.patron_chamber, b.patron_name_formatted AS patron, b.summary, b.status_history,
            v.code AS version, v.source_date, s.heading, c.text AS excerpt,
            '/bills/sections/' || s.id AS citation";
        $joins = ' FROM rsun_ai_chunk c JOIN bill_section s ON s.id=c.section_id
            JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id ';
        $terms = BillSearchResult::query($query);
        // Best matching chunk per section before applying the fixed candidate window.
        $lexical = $this->db->fetchAllAssociative("SELECT * FROM (SELECT DISTINCT ON (s.id) ".$fields.
            ", c.text <@> to_bm25query(?, 'rsun_ai_chunk_bm25') AS search_rank".$joins.
            " WHERE $where AND c.search_vector @@ websearch_to_tsquery('english', ?)
            ORDER BY s.id, search_rank, c.id) hits ORDER BY search_rank, id LIMIT 500",
            [$terms, ...$params, $terms]);
        $vector = $this->embeddings->query('task: search result | query: '.$query);
        $semantic = $this->db->fetchAllAssociative("SELECT * FROM (SELECT DISTINCT ON (s.id) ".$fields.
            ", e.embedding <=> ?::vector AS search_rank".$joins.
            " JOIN rsun_ai_embedding e ON e.id=c.embedding_id
            WHERE $where AND e.status='complete' AND e.model=?
            ORDER BY s.id, search_rank, c.id) hits ORDER BY search_rank, id LIMIT 500",
            [$vector, ...$params, BillEmbeddings::MODEL]);
        $hits = self::fuseAll($lexical, $semantic);
        foreach ($hits as &$hit) unset($hit['search_rank']);
        return array_slice(BillSearchResult::group($hits, $query), $offset, $limit);
    }

    /** Rank fusion counts a section once per retrieval method, not once per chunk. */
    public static function fuse(array ...$lists): array
    {
        return array_slice(self::fuseAll(...$lists), 0, 10);
    }

    private static function fuseAll(array ...$lists): array
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
        return array_map(static fn ($id) => $hits[$id], array_keys($scores));
    }

    public function section(string $id): array
    {
        [$year, $billId] = $this->scope(0, 0);
        $section = $this->db->fetchAssociative("SELECT s.*, b.id AS bill_id, b.number, b.year, b.status, b.outcome,
            b.chamber, b.patron_chamber, b.patron_name_formatted AS patron, b.summary, b.status_history,
            v.code AS version, v.source_date, v.source_url, '/bills/sections/' || s.id AS citation
            FROM bill_section s JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id WHERE s.id=? AND (?=0 OR b.year=?) AND (?=0 OR b.id=?)", [$id, $year, $year, $billId, $billId]) ?: [];
        if (!$section) return [];
        $section = BillSearchResult::metadata($section);
        unset($section['summary']);
        return $section;
    }
    public function bill(int $id): array
    {
        [$year, $scopeId] = $this->scope(0, 0);
        $bill = $this->db->fetchAssociative("SELECT id,year,number,catch_line AS title,summary,patron_name_formatted AS patron,
            status,outcome,chamber,patron_chamber,status_history,version,text AS version_references,full_text FROM bill
            WHERE id=? AND (?=0 OR year=?) AND (?=0 OR id=?)", [$id,$year,$year,$scopeId,$scopeId]);
        if (!$bill) return [];
        $bill = BillSearchResult::metadata($bill);
        $bill['citation'] = '/bills/'.$id;
        $bill['sections'] = $this->db->fetchAllAssociative('SELECT s.id,s.heading,v.code AS version FROM bill_section s JOIN bill_version v ON v.id=s.version_id WHERE v.bill_id=? ORDER BY v.source_date,s.position', [$id]);
        $bill['version_references'] = json_decode($bill['version_references'] ?? '[]', true);
        $bill['full_text_truncated'] = strlen($bill['full_text'] ?? '') > 100000;
        $bill['full_text'] = mb_strcut($bill['full_text'] ?? '', 0, 100000, 'UTF-8');
        return $bill;
    }

    public function summaries(string $query, int $year = 2026, int $billId = 0, int $limit = 20, int $offset = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null): array
    {
        [$year, $billId] = $this->scope($year, $billId);
        self::validate($query, $limit, $offset);
        if ('' === trim($query)) return [];
        [$where, $params] = self::filters($year, $billId, $outcome, $status, $chamber, $patron);
        $query = BillSearchResult::query($query);
        // Search titles preferentially; an agency name alone is not a tobacco topic.
        $document = "setweight(to_tsvector('english',concat_ws(' ',b.number,b.catch_line)), 'A') ||
            setweight(to_tsvector('english',regexp_replace(regexp_replace(coalesce(b.summary,''), '<[^>]*>', ' ', 'g'),
            'Bureau of Alcohol, Tobacco, Firearms( and Explosives)?', '', 'gi')), 'B')";
        $rows = $this->db->fetchAllAssociative("SELECT b.id,b.year,b.number,b.catch_line AS title,b.summary,
            b.patron_name_formatted AS patron,b.patron_chamber,b.chamber,b.status,b.outcome,b.status_history,
            b.version,'/bills/' || b.id AS citation FROM bill b
            WHERE $where AND ($document) @@ websearch_to_tsquery('english',?)
            ORDER BY ts_rank_cd(($document),websearch_to_tsquery('english',?)) DESC,b.id LIMIT $limit OFFSET $offset",
            [...$params,$query,$query]);
        return array_map(BillSearchResult::metadata(...), $rows);
    }

    private static function validate(string $query, int $limit, int $offset): void
    {
        if (mb_strlen($query) > 500) throw new \InvalidArgumentException('Query exceeds 500 characters.');
        if ($limit < 1 || $limit > 50 || $offset < 0 || $offset > 200) {
            throw new \InvalidArgumentException('Use limit 1–50 and offset 0–200.');
        }
    }

    private static function filters(int $year, int $billId, ?string $outcome, ?string $status, ?string $chamber, ?string $patron): array
    {
        $where = 'b.year=? AND (?=0 OR b.id=?)';
        $params = [$year,$billId,$billId];
        if (null !== $outcome) {
            if (!in_array($outcome, ['passed','failed','vetoed','incorporated'], true)) throw new \InvalidArgumentException('Unknown outcome.');
            $where .= " AND (CASE WHEN b.status='incorporated' THEN 'incorporated' WHEN b.status LIKE '%vetoed%' THEN 'vetoed' ELSE b.outcome END)=?";
            $params[] = $outcome;
        }
        if (null !== $status) { $where .= ' AND b.status=?'; $params[] = $status; }
        if (null !== $chamber) {
            if (!in_array($chamber, ['house','senate'], true)) throw new \InvalidArgumentException('Chamber must be house or senate.');
            $where .= ' AND b.chamber=?'; $params[] = $chamber;
        }
        if (null !== $patron) {
            $where .= " AND strpos(lower(coalesce(b.patron_name_formatted,'')),lower(?))>0"; $params[] = $patron;
        }
        return [$where,$params];
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
