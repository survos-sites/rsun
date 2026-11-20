<?php

declare(strict_types=1);
namespace App\Tests\Service;
use App\Service\BillRetrieval;
use PHPUnit\Framework\TestCase;
final class BillRetrievalTest extends TestCase
{
    public function testRankFusionDoesNotRewardDuplicateChunks(): void
    {
        $results=BillRetrieval::fuse([['id'=>'a'],['id'=>'a'],['id'=>'b']],[['id'=>'b'],['id'=>'c']]);
        self::assertSame(['b','a','c'],array_column($results,'id'));
    }
}
