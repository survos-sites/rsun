<?php

declare(strict_types=1);
namespace App\Service;

/** Derived discovery text only; the complete source remains available for citations. */
final class BillDiscoveryText
{
    public function fromSection(string $html, string $heading, string $fallback): string
    {
        $html = preg_replace('/<em\b[^>]*class=["\']new["\'][^>]*>(.*?)<\/(?:ins|em)>/is', '<ins>$1</ins>', $html);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($doc);
        $paragraphs = $xpath->query('//body/p');
        if (!$paragraphs->length) return $fallback;
        $definitions = (bool) preg_match('/\bdefinitions?\b/i', $heading);
        $kept = []; $removed = false;
        foreach ($paragraphs as $p) {
            $text = trim(preg_replace('/\s+/u', ' ', $p->textContent));
            $isDefinition = $definitions || preg_match('/^["“].+?["”]\s+(?:means|includes|shall mean)\b/u', $text);
            $changed = $xpath->query('.//ins|.//del|.//s', $p)->length > 0;
            if ($isDefinition && !$changed) { $removed = true; continue; }
            // Preserve removed wording too when a definition changes; it describes the change.
            $kept[] = $text;
        }
        return $removed ? implode("\n\n", $kept) : $fallback;
    }
}
