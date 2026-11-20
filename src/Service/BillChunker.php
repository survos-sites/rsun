<?php

declare(strict_types=1);
namespace App\Service;

/** Keep paragraphs/definitions intact where possible; bytes bound transport, provider counts tokens. */
final class BillChunker
{
    public function split(string $text, int $maxBytes = 6000): array
    {
        if ($maxBytes < 100) throw new \InvalidArgumentException('Chunk size too small');
        $pieces = [];
        foreach (preg_split('/\n\s*\n/u', trim($text)) as $paragraph) {
            if (strlen($paragraph) <= $maxBytes) { $pieces[] = $paragraph; continue; }
            $sentences = \IntlBreakIterator::createSentenceInstance('en_US');
            $sentences->setText($paragraph);
            foreach ($sentences->getPartsIterator() as $sentence) {
                while (strlen($sentence) > $maxBytes) {
                    $part = mb_strcut($sentence, 0, $maxBytes, 'UTF-8');
                    $space = strrpos($part, ' ');
                    if (false !== $space && $space > $maxBytes / 2) $part = substr($part, 0, $space);
                    $pieces[] = $part;
                    $sentence = ltrim(substr($sentence, strlen($part)));
                }
                if ('' !== trim($sentence)) $pieces[] = trim($sentence);
            }
        }
        $chunks = []; $chunk = '';
        foreach ($pieces as $piece) {
            if ('' !== $chunk && strlen($chunk."\n\n".$piece) > $maxBytes) { $chunks[] = $chunk; $chunk = ''; }
            $chunk .= ('' === $chunk ? '' : "\n\n").$piece;
        }
        if ('' !== $chunk) $chunks[] = $chunk;
        return $chunks;
    }
}
