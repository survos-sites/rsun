<?php

declare(strict_types=1);

namespace App\LocalApiResource;

use ApiPlatform\Metadata\ApiResource;
use Survos\SearchBundle\ApiPlatform\SearchOperation;

#[ApiResource(operations: [
    new SearchOperation('/search/bills/duckdb', 'app_bill', 'api_bill_search_duckdb'),
])]
final class DuckDbBillSearchResult
{
}
