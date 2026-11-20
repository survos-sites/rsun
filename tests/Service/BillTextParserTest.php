<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BillTextParser;
use PHPUnit\Framework\TestCase;

final class BillTextParserTest extends TestCase
{
    public function testSourceAndChangesArePreservedSeparatelyFromProposedWording(): void
    {
        $source = '<p>Intro</p><p>§&nbsp;40.1-28.10. Minimum wages.</p><p>Pay <s>$9.50</s><em class="new">$12.77</ins> per hour.</p>';
        $document = (new BillTextParser())->parse($source);
        self::assertSame($source, $document['sourceHtml']);
        self::assertCount(2, $document['sections']);
        $section = $document['sections'][1];
        self::assertSame('section-1', $section['anchor']);
        self::assertStringContainsString('$9.50', $section['text']);
        self::assertStringNotContainsString('$9.50', $section['proposedText']);
        self::assertStringContainsString('$12.77', $section['proposedText']);
        self::assertSame(['deletion', 'addition'], array_column($section['changes'], 'kind'));
    }
    public function testChangeOrderAndLineBreaksArePreserved(): void
    {
        $document = (new BillTextParser())->parse('<p>before<br>after <ins>new</ins><del>old</del><ins>newer</ins></p>');
        self::assertSame(['addition', 'deletion', 'addition'], array_column($document['sections'][0]['changes'], 'kind'));
        self::assertStringContainsString('before after', $document['sections'][0]['text']);
    }
}
