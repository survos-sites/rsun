<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BillReader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BillReaderTest extends KernelTestCase
{
    public function testStructureAndAmendmentsRemainSafeAndReadable(): void
    {
        self::bootKernel();
        $reader = self::getContainer()->get(BillReader::class);
        $result = $reader->render('<p>§ <a href="https://vacode.org/58.1-1021.01">58.1-1021.01</a>. Definitions.</p><p>“Cigar” means <s>old wording</s><ins>new wording</ins>.</p><p>A. First provision.</p><script>alert(1)</script><p onclick="alert(1)">Safe <a href="javascript:alert(1)">link</a>.</p>', 'bill-62155');
        self::assertCount(1, $result['sections']);
        self::assertCount(2, $result['sections'][0]['children']);
        self::assertSame('“Cigar”', $result['sections'][0]['children'][0]['title']);
        self::assertSame('bill-62155-section-1', $result['sections'][0]['id']);
        self::assertStringContainsString('<strong class="akn-def">“Cigar”</strong>', $result['html']);
        self::assertStringContainsString('<s>old wording</s><ins>new wording</ins>', $result['html']);
        self::assertStringContainsString('bill-subsection', $result['html']);
        self::assertStringNotContainsString('<script', $result['html']);
        self::assertStringNotContainsString('onclick', $result['html']);
        self::assertStringNotContainsString('javascript:', $result['html']);
        self::assertStringContainsString('https://vacode.org/58.1-1021.01', $result['html']);
    }

    public function testAmendedDefinitionTermIsNotFlattened(): void
    {
        self::bootKernel();
        $reader = self::getContainer()->get(BillReader::class);
        $result = $reader->render('<p><ins>“New term”</ins> means something.</p>');
        self::assertStringContainsString('<ins>“New term”</ins>', $result['html']);
        self::assertStringContainsString('bill-definition', $result['html']);
    }
    public function testLongBillIsNotTruncatedBySanitizer(): void
    {
        self::bootKernel();
        $reader = self::getContainer()->get(BillReader::class);
        $source = '<p>§ 1. Definitions.</p>'.str_repeat('<p>“Term” means a defined phrase.</p>', 1000)
            .'<p>§ 2. Enforcement.</p><p>FINAL PROVISION <script>alert(1)</script></p>';
        $result = $reader->render($source);
        self::assertCount(2, $result['sections']);
        self::assertStringContainsString('FINAL PROVISION', $result['html']);
        self::assertStringNotContainsString('<script', $result['html']);
    }
}
