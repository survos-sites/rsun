<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Bill;
use Survos\SearchBundle\Attribute\AsSearch;
use Survos\SearchBundle\Search\AbstractSearch;
use Survos\SearchBundle\Twig\Components\Facet\RefinementList;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[AsSearch(index: Bill::class, name: 'app_bill', adapter: 'duckdb')]
final class BillDuckDbSearch extends AbstractSearch implements \Survos\SearchBundle\Search\HighlightSearchInterface
{
    public function getHighlightFields(): array
    {
        return ['catchLine', 'summary'];
    }

    public function build(array $options = []): void
    {
        $columns = [
            'year' => 'd.year', 'chamber' => 'd.chamber', 'status' => 'd.status',
            'outcome' => 'd.outcome', 'patronParty' => 'd.patron_party',
            'committeeShortname' => 'd.committee',
        ];
        foreach ($columns as $property => $column) {
            $this->addFacet($property, $property, RefinementList::class);
        }
        $this->setAvailableHitsPerPage([24, 48, 96]);
        $this->addAvailableSort('', 'Relevance')
            ->addAvailableSort('year_desc', 'Newest session')
            ->addAvailableSort('year_asc', 'Oldest session')
            ->addAvailableSort('number_asc', 'Bill number');
        $this->setAdapterParameters([
            'table' => 'bills',
            'selectColumns' => ['d.id', 'd.year', 'd.number', 'd.chamber', 'd.status', 'd.outcome',
                'd.catch_line AS "catchLine"', 'd.patron_name AS "patronNameFormatted"', 'd.summary',
                'd.patron_party AS "patronParty"', 'd.committee AS "committeeShortname"'],
            'facetColumns' => $columns,
            'sortColumns' => ['year_desc' => 'd.year DESC', 'year_asc' => 'd.year ASC', 'number_asc' => 'd.number ASC'],
            'ftsFunction' => 'fts_main_bills.match_bm25',
            'ftsConjunctive' => true,
        ]);
    }
}
