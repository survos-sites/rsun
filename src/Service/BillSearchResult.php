<?php

declare(strict_types=1);

namespace App\Service;

/** Compact, source-preserving presentation shared by MCP and site chat. */
final class BillSearchResult
{
    public static function query(string $query): string
    {
        return match (mb_strtolower(trim($query))) {
            'vaping', 'vape', 'vapes', 'e-cigarette', 'e-cigarettes', 'electronic cigarettes' => 'vaping OR "vape pen" OR "nicotine vapor" OR "electronic cigarette"',
            'tobacco' => 'tobacco OR nicotine OR cigarette OR cigar',
            default => $query,
        };
    }

    public static function excerpt(string $text, string $query, int $length = 480): string
    {
        $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        preg_match_all('/[\p{L}\p{N}]+/u', self::query($query), $matches);
        $position = false;
        foreach ($matches[0] as $term) {
            if (mb_strlen($term) < 3 || in_array(strtolower($term), ['and', 'the', 'for', 'with'])) continue;
            $found = false;
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu', $text, $match, PREG_OFFSET_CAPTURE)) {
                $found = mb_strlen(substr($text, 0, $match[0][1]));
            }
            if (false !== $found && (false === $position || $found < $position)) $position = $found;
        }
        $start = max(0, (false === $position ? 0 : $position) - 100);
        return ($start ? '…' : '').mb_substr($text, $start, $length).(mb_strlen($text) > $start + $length ? '…' : '');
    }

    public static function metadata(array $row): array
    {
        $row['archive_outcome'] = $row['outcome'] ?? null;
        $row['incorporated_into'] = null;
        if ('incorporated' === ($row['status'] ?? null)) {
            $row['outcome'] = 'incorporated';
            $history = $row['status_history'] ?? [];
            if (is_string($history)) $history = json_decode($history, true) ?? [];
            $evidence = $row['summary'] ?? '';
            foreach ($history as $action) {
                if ('incorporated' === ($action['translation'] ?? null)) $evidence .= ' '.($action['status'] ?? '');
            }
            if (preg_match('/(?:incorporated into\s+|Incorporated[^\(]*\()((?:SB|HB)\s*\d+)/i', $evidence, $m)) {
                $row['incorporated_into'] = ['number' => strtolower(preg_replace('/\s+/', '', $m[1])), 'year' => (int) $row['year']];
            }
        } elseif (str_contains($row['status'] ?? '', 'vetoed')) {
            $row['outcome'] = 'vetoed';
        }
        if (!empty($row['patron_chamber']) && !empty($row['chamber']) && $row['patron_chamber'] !== $row['chamber']) {
            $row['data_quality_notes'][] = 'Archive patron chamber conflicts with bill chamber; patron title is unverified.';
        }
        unset($row['status_history']);
        return $row;
    }

    /** Group Code headings for navigation, never treat different bill versions as identical text. */
    public static function group(array $hits, string $query): array
    {
        $groups = [];
        foreach ($hits as $hit) {
            $hit = self::metadata($hit);
            $hit['excerpt'] = self::excerpt($hit['excerpt'], $query);
            unset($hit['summary']);
            $key = preg_match('/^§\s*([\d.]+-[\d.:]+)/u', $hit['heading'], $m) ? $hit['year'].':'.$m[1] : $hit['id'];
            $groups[$key] ??= $hit + ['matching_bills' => []];
            $groups[$key]['matching_bills'][] = array_intersect_key($hit, array_flip(['id','bill_id','number','year','version','status','outcome','archive_outcome','incorporated_into','citation','data_quality_notes']));
        }
        foreach ($groups as &$group) {
            $group['matching_bill_count'] = count(array_unique(array_column($group['matching_bills'], 'bill_id')));
            $group['matching_version_count'] = count($group['matching_bills']);
            $group['matching_versions_truncated'] = count($group['matching_bills']) > 8;
            $group['matching_bills'] = array_slice($group['matching_bills'], 0, 8);
            $group['group_note'] = 'Same Code section; bill versions can differ. Read each cited section before comparing; use billId to search a specific bill. Counts describe retrieved candidates, not all bills in the session.';
        }
        return array_values($groups);
    }
}
