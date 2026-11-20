<?php

declare(strict_types=1);
namespace App\Tests\Service;
use App\Service\BillDiscoveryText;
use PHPUnit\Framework\TestCase;
final class BillDiscoveryTextTest extends TestCase
{
    public function testUnchangedDefinitionsAreExcludedButChangesRetainContext(): void
    {
        $html='<p>§ 1. Definitions.</p><p>"Person" means an individual.</p><p>"Farm" means land with <s>ten</s><ins>five</ins> acres.</p>';
        $text=(new BillDiscoveryText())->fromSection($html,'§ 1. Definitions.','all text');
        self::assertStringNotContainsString('Person',$text);
        self::assertStringContainsString('"Farm" means land with tenfive acres.',$text);
    }
    public function testSourceMalformedAdditionIsRetained(): void
    {
        $text=(new BillDiscoveryText())->fromSection('<p>"Person" means anyone.</p><p><em class="new">"Agrivoltaics" means farming with solar.</ins></p>','Definitions','fallback');
        self::assertSame('"Agrivoltaics" means farming with solar.',$text);
    }
    public function testOrdinaryProvisionsReuseExistingEmbeddingText(): void
    {
        self::assertSame('existing proposed text',(new BillDiscoveryText())->fromSection('<p>A tax is imposed.</p>','Tax','existing proposed text'));
        self::assertSame('',(new BillDiscoveryText())->fromSection('<p>"Person" means anyone.</p>','Definitions','fallback'));
    }
}
