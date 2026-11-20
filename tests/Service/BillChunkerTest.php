<?php

declare(strict_types=1);
namespace App\Tests\Service;
use App\Service\BillChunker;
use PHPUnit\Framework\TestCase;
final class BillChunkerTest extends TestCase
{
    public function testSmallDefinitionStaysWhole(): void
    {
        $text='“Minor” means a person under eighteen years of age.';
        self::assertSame([$text], (new BillChunker())->split($text));
    }
    public function testLongUnicodeTextRemainsCompleteWithinByteBudget(): void
    {
        $text=str_repeat('“Tobacco” means a product. No tax is due in this example. ', 100);
        $chunks=(new BillChunker())->split($text, 200);
        foreach($chunks as $chunk) { self::assertLessThanOrEqual(200,strlen($chunk)); self::assertTrue(mb_check_encoding($chunk,'UTF-8')); }
        self::assertSame(preg_replace('/\s+/u','', $text),preg_replace('/\s+/u','',implode(' ',$chunks)));
    }
}
