<?php

declare(strict_types=1);

namespace App\Search;

use Survos\SearchBundle\Attribute\AsSearch;
use Survos\SearchBundle\Search\AbstractSearch;

#[AsSearch(index: 'bill', name: 'app_bill_es', adapter: 'es')]
final class BillElasticSearch extends AbstractSearch implements \Survos\SearchBundle\Search\HighlightSearchInterface
{
    public function getHighlightFields(): array
    {
        return ['catchLine', 'summary'];
    }

    public function build(array $options = []): void
    {
        $duck = (new BillDuckDbSearch())->create();
        $facets = [];
        foreach ($duck->getFacets() as $facet) {
            $field = $facet->getProperty();
            $this->addFacet($field, $facet->getLabel(), $facet->getDisplayComponent());
            $facets[$field] = 'year' === $field ? $field : $field.'.keyword';
        }
        $this->setAvailableHitsPerPage([24, 48, 96]);
        $this->addAvailableSort('', 'Relevance');
        $this->setAdapterParameters([
            'index' => 'bill',
            'searchFields' => ['number', 'catchLine', 'summary', 'fullText', 'patronNameFormatted'],
            'sourceFields' => ['id', 'year', 'number', 'chamber', 'status', 'outcome', 'catchLine', 'summary', 'patronNameFormatted'],
            'facetFields' => $facets,
            // Source summaries contain HTML; use the shared plain-text highlighter.
            'highlight' => false,
            'fuzziness' => '0', 'prefixSearch' => false,
        ]);
    }
}
