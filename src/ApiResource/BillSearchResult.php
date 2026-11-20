<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use Survos\SearchBundle\ApiPlatform\SearchOperation;

#[ApiResource(operations: [
    new SearchOperation('/search/bills/postgres', 'app_bill_pg', 'api_bill_search_postgres'),
    new SearchOperation('/search/bills/es', 'app_bill_es', 'api_bill_search_es'),
])]
final class BillSearchResult
{
}
