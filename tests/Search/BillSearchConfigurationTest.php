<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Search\BillDuckDbSearch;
use Survos\SearchBundle\Adapter\AdapterProvider;
use Survos\SearchBundle\Adapter\DuckDb\DuckDbAdapter;
use Survos\SearchBundle\Search\SearchProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BillSearchConfigurationTest extends KernelTestCase
{
    public function testExplicitDuckDbSearchOverridesAutomaticEntitySearch(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $search = $container->get(SearchProvider::class)->getSearch('app_bill')->create();
        self::assertInstanceOf(BillDuckDbSearch::class, $search);
        self::assertInstanceOf(DuckDbAdapter::class, $container->get(AdapterProvider::class)->getAdapter($search->getAdapterName()));
        self::assertTrue($search->getAdapterParameters()['ftsConjunctive']);
        self::assertSame(['year', 'chamber', 'status', 'outcome', 'patronParty', 'committeeShortname'], array_map(static fn ($facet) => $facet->getProperty(), $search->getFacets()));
        self::assertCount(6, $search->create()->getFacets());
    }
}
