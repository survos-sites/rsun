<?php

declare(strict_types=1);
namespace App\Tests\Service;

use App\Service\BillSearchResult;
use PHPUnit\Framework\TestCase;

final class BillSearchResultTest extends TestCase
{
    public function testAliasesDoNotBroadenArbitraryBooleanQueries(): void
    {
        self::assertStringContainsString('"nicotine vapor"', BillSearchResult::query('e-cigarettes'));
        self::assertSame('housing OR tax', BillSearchResult::query('housing OR tax'));
        self::assertStringNotContainsString('smoke', BillSearchResult::query('tobacco'));
    }

    public function testExcerptFindsLateMatchAndBoundsUnicodeText(): void
    {
        $text = str_repeat('Winery definitions. ', 400).'nicotine vapor product '.str_repeat('é', 600);
        $excerpt = BillSearchResult::excerpt($text, 'vaping');
        self::assertStringContainsString('nicotine vapor', $excerpt);
        self::assertLessThanOrEqual(482, mb_strlen($excerpt));
        self::assertTrue(mb_check_encoding($excerpt));
    }

    public function testGroupingPreservesDifferentBillCitations(): void
    {
        $base = ['year'=>2026,'heading'=>'§ 18.2-371.2. Definitions.','excerpt'=>'nicotine vapor','status'=>'incorporated'];
        $hits = BillSearchResult::group([
            $base + ['id'=>'one','bill_id'=>1,'number'=>'sb826','citation'=>'/one','version'=>'S1'],
            $base + ['id'=>'two','bill_id'=>2,'number'=>'sb542','citation'=>'/two','version'=>'S2'],
            array_replace($base, ['id'=>'three','bill_id'=>3,'heading'=>'§ 4.1-100. Definitions.'])
        ], 'vaping');
        self::assertCount(2,$hits);
        self::assertSame(2,$hits[0]['matching_bill_count']);
        self::assertSame(['/one','/two'],array_column($hits[0]['matching_bills'],'citation'));
    }

    public function testIncorporationAndPatronQualityPreserveArchiveValues(): void
    {
        $row = BillSearchResult::metadata(['year'=>2026,'status'=>'incorporated','outcome'=>null,
            'summary'=>'This bill was incorporated into SB 542.', 'patron'=>'Del. Example',
            'chamber'=>'senate','patron_chamber'=>'house']);
        self::assertSame('incorporated',$row['outcome']);
        self::assertNull($row['archive_outcome']);
        self::assertSame(['number'=>'sb542','year'=>2026],$row['incorporated_into']);
        self::assertSame('Del. Example',$row['patron']);
        self::assertNotEmpty($row['data_quality_notes']);
    }

    public function testIncorporationHistoryFallbackAndVeto(): void
    {
        $row=BillSearchResult::metadata(['year'=>2026,'status'=>'incorporated','status_history'=>json_encode([
            ['translation'=>'incorporated','status'=>'Incorporated by Finance (SB542-Aird)']])]);
        self::assertSame('sb542',$row['incorporated_into']['number']);
        self::assertArrayNotHasKey('status_history',$row);
        $veto=BillSearchResult::metadata(['status'=>'vetoed by governor','outcome'=>'failed']);
        self::assertSame('vetoed',$veto['outcome']);
        self::assertSame('failed',$veto['archive_outcome']);
    }
}
