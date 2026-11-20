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
            'youth', 'young people', 'children', 'minors' => 'youth OR child OR minor OR juvenile OR adolescent',
            'guns', 'gun', 'firearms' => 'gun OR firearm OR handgun OR ammunition',
            'immigration' => 'immigration OR immigrant OR alien OR "federal immigration enforcement"',
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

    public static function coverage(array $bill): array
    {
        $hasText = '' !== trim($bill['full_text'] ?? '');
        $current = strtoupper($bill['version'] ?? '');
        $available = [];
        foreach ($bill['versions'] ?? [] as $version) {
            if (filter_var($version['text_available'], FILTER_VALIDATE_BOOLEAN)) $available[] = strtoupper($version['code']);
        }
        foreach ($bill['version_references'] as &$reference) {
            $reference['text_available'] = in_array(strtoupper($reference['number'] ?? ''), $available, true) || ($hasText && strtoupper($reference['number'] ?? '') === $current);
            $reference['body_association'] = $reference['text_available'] ? (strtoupper($reference['number'] ?? '') === $current ? 'archive-reported-current-version' : 'stored-version-body') : 'metadata-only';
        }
        unset($reference);
        $bill['text_coverage'] = ['current_archive_text_available'=>$hasText,
            'historical_version_bodies_available'=>(bool) array_diff($available, [$current]), 'separate_amendment_documents_available'=>false,
            'note'=>'Inline additions/deletions describe changes to existing law. They are not a complete amendment or version history.'];
        return $bill;
    }

    /** Retain definition evidence but reduce its dominance for general topic searches. */
    public static function prioritize(array $groups, string $query): array
    {
        $definitionQuery = (bool) preg_match('/\b(defin\w*|meaning|means)\b/i', $query);
        foreach ($groups as $rank => &$group) {
            $group['section_kind'] = preg_match('/\bdefinitions?\b/i', $group['heading']) ? 'definitions' : 'provision';
            $group['_priority'] = (!$definitionQuery && 'definitions' === $group['section_kind'] ? 0.35 : 1) / (1 + $rank);
            $group['_rank'] = $rank;
        }
        unset($group);
        usort($groups, static fn ($a, $b) => ($b['_priority'] <=> $a['_priority']) ?: ($a['_rank'] <=> $b['_rank']));
        foreach ($groups as &$group) unset($group['_priority'], $group['_rank']);
        return $groups;
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
            $groups[$key]['matching_bills'][] = array_intersect_key($hit, array_flip(['id','bill_id','number','year','title','patron','patron_party','patron_district','date_introduced','version','status','outcome','archive_outcome','incorporated_into','citation','data_quality_notes']));
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
