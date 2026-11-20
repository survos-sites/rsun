<?php

declare(strict_types=1);

namespace App\Search;

use Survos\SearchBundle\Attribute\AsSearch;
use Survos\SearchBundle\Search\AbstractSearch;

#[AsSearch(index: 'rsun_bill_search', name: 'app_bill_pg', adapter: 'pg_bm25')]
final class BillPostgresSearch extends AbstractSearch implements \Survos\SearchBundle\Search\HighlightSearchInterface
{
    public function getHighlightFields(): array
    {
        return ['catchLine', 'summary'];
    }

    public function build(array $options = []): void
    {
        $duck = (new BillDuckDbSearch())->create();
        $p = $duck->getAdapterParameters();
        foreach ($duck->getFacets() as $facet) {
            $this->addFacet($facet->getProperty(), $facet->getLabel(), $facet->getDisplayComponent());
        }
        $this->setAvailableHitsPerPage([24, 48, 96]);
        $this->addAvailableSort('', 'Relevance');
        $this->setAdapterParameters([
            'table' => 'rsun_bill_search', 'idColumn' => 'id',
            'selectColumns' => $p['selectColumns'], 'facetColumns' => $p['facetColumns'],
            'matchExpression' => "d.search_vector @@ plainto_tsquery('english', :bm25Query)",
            'scoreExpression' => "-(d.search_text <@> to_bm25query(:bm25Query, 'rsun_bill_search_bm25'))",
        ]);
    }
}
