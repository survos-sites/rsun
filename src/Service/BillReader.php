<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/** Presentation projection only: the stored bill source is never rewritten. */
final class BillReader
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.bill_text')]
        private HtmlSanitizerInterface $sanitizer,
    ) {}

    /** @return array{html: string, sections: list<array{id: string, title: string, children: array}>} */
    public function render(string $source, string $prefix = 'bill'): array
    {
        $source = preg_replace('/<em\b[^>]*class=["\']new["\'][^>]*>(.*?)<\/(?:ins|em)>/is', '<ins>$1</ins>', $source);
        $safe = $this->sanitizer->sanitize(str_replace('\\r', "\n", $source));
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$safe.'</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $sections = [];
        $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '-', $prefix);
        foreach (iterator_to_array($doc->getElementsByTagName('p')) as $p) {
            $text = trim(preg_replace('/\s+/u', ' ', $p->textContent));
            $p->setAttribute('class', 'akn-p');
            if (preg_match('/^§\s*\d[\d.:-]*\.?\s+/u', $text)) {
                $id = $prefix.'-section-'.(count($sections) + 1);
                $heading = $doc->createElement('h3');
                $heading->setAttribute('id', $id);
                $heading->setAttribute('class', 'akn-heading bill-section-heading');
                while ($p->firstChild) {
                    $heading->appendChild($p->firstChild);
                }
                $p->parentNode->replaceChild($heading, $p);
                $sections[] = ['id' => $id, 'title' => $text, 'children' => []];
                continue;
            }
            if (preg_match('/^(["“][^"”]+["”](?:\s+(?:or|and)\s+["“][^"”]+["”])*)\s+(?:means|includes|shall mean)\b/u', $text, $definition)) {
                $p->setAttribute('class', 'akn-p bill-definition');
                $this->addEntry($p, $sections, $definition[1]);
                // Wrap only an existing text node; never flatten amendment markup.
                $first = $p->firstChild;
                if ($first instanceof \DOMText && preg_match('/^(\s*["“][^"”]+["”](?:\s+(?:or|and)\s+["“][^"”]+["”])*)/u', $first->data, $match)) {
                    $term = $doc->createElement('strong');
                    $term->setAttribute('class', 'akn-def');
                    $term->appendChild($doc->createTextNode($match[1]));
                    $first->deleteData(0, mb_strlen($match[1]));
                    $p->insertBefore($term, $first);
                }
            } elseif (preg_match('/^[A-Z]\.\s/u', $text)) {
                $p->setAttribute('class', 'akn-p bill-subsection');
                $this->addEntry($p, $sections, mb_strimwidth($text, 0, 90, '…'));
            } elseif (preg_match('/^(?:\d+|[a-z])\.\s/u', $text)) {
                $p->setAttribute('class', 'akn-p bill-numbered');
            }
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        $html = '';
        foreach ($body->childNodes as $node) {
            $html .= $doc->saveHTML($node);
        }
        return ['id' => $prefix, 'html' => $html, 'sections' => $sections];
    }
    private function addEntry(\DOMElement $paragraph, array &$sections, string $title): void
    {
        if (!$sections) return;
        $index = array_key_last($sections);
        $id = $sections[$index]['id'].'-item-'.(count($sections[$index]['children']) + 1);
        $paragraph->setAttribute('id', $id);
        $sections[$index]['children'][] = ['id' => $id, 'title' => $title];
    }
}
