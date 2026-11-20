<?php

declare(strict_types=1);

namespace App\Service;

/** Lossless source plus derived, ordered sections for retrieval and citations. */
final class BillTextParser
{
    public function parse(string $source): array
    {
        // Richmond Sunlight exports sometimes pair an em.new opener with an ins closer.
        $normalized = preg_replace('/<em\b[^>]*class=["\']new["\'][^>]*>(.*?)<\/(?:ins|em)>/is', '<ins>$1</ins>', $source);
        $normalized = str_replace('\\r', "\n", $normalized);
        $normalized = preg_replace('/<br\s*\/?\s*>/i', "<br>\n", $normalized);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $old = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$normalized.'</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        $sections = [];
        $current = ['anchor' => 'preamble', 'heading' => 'Preamble', 'html' => '', 'text' => '', 'proposedText' => '', 'changes' => []];
        $sectionNumber = 0;
        foreach ($body->childNodes as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent));
            if ('' === $text) {
                continue;
            }
            if (preg_match('/^§\s*\d[\d.:-]*\.?\s+/u', $text)) {
                if ('' !== $current['html']) {
                    $sections[] = $current;
                }
                $current = ['anchor' => 'section-'.++$sectionNumber, 'heading' => $text, 'html' => '', 'text' => '', 'proposedText' => '', 'changes' => []];
            }
            $current['html'] .= $doc->saveHTML($node);
            $current['text'] .= $text."\n\n";
            array_push($current['changes'], ...$this->changes($node));
            $copy = $node->cloneNode(true);
            if ($copy instanceof \DOMElement) {
                foreach (['s', 'del', 'ins'] as $tag) {
                    $nodes = iterator_to_array($copy->getElementsByTagName($tag));
                    if ($copy->tagName === $tag) {
                        array_unshift($nodes, $copy);
                    }
                    foreach ($nodes as $change) {
                        if ('ins' !== $tag && $change->parentNode) {
                            $change->parentNode->removeChild($change);
                        } elseif ('ins' !== $tag && $change === $copy) {
                            $copy->textContent = '';
                        }
                    }
                }
            }
            $current['proposedText'] .= trim(preg_replace('/\s+/u', ' ', $copy->textContent))."\n\n";
        }
        if ('' !== $current['html']) {
            $sections[] = $current;
        }
        return ['sourceHtml' => $source, 'sourceHash' => hash('sha256', $source), 'sections' => $sections];
    }
    private function changes(\DOMNode $node): array
    {
        $changes = [];
        if ($node instanceof \DOMElement && in_array($node->tagName, ['s', 'del', 'ins'], true)) {
            $changes[] = ['kind' => 'ins' === $node->tagName ? 'addition' : 'deletion', 'text' => $node->textContent];
        }
        foreach ($node->childNodes as $child) array_push($changes, ...$this->changes($child));
        return $changes;
    }
}
