<?php

declare(strict_types=1);

namespace App\Tests\Search;

use Survos\SearchBundle\Adapter\AdapterProvider;
use Survos\SearchBundle\Adapter\Elasticsearch\ElasticsearchAdapter;
use Survos\SearchBundle\Search\SearchProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BillSearchConfigurationTest extends KernelTestCase
{
    public function testBillSearchUsesElasticsearchAndPreservesSearchFields(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $adapter = $container->get(AdapterProvider::class)->getAdapter();
        self::assertInstanceOf(ElasticsearchAdapter::class, $adapter);

        $search = $container->get(SearchProvider::class)->getSearch('app_bill')->create();
        $parameters = $search->getAdapterParameters();
        self::assertContains('fullText', $parameters['searchFields']);
        self::assertContains('catchLine', $parameters['searchFields']);
        $facets = array_map(static fn ($facet) => $facet->getProperty(), $search->getFacets());
        self::assertContains('year', $facets);
        self::assertContains('chamber', $facets);
        self::assertContains('status', $facets);
    }
}
