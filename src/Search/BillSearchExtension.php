<?php

declare(strict_types=1);
namespace App\Search;

use ApiPlatform\Elasticsearch\Extension\RequestBodySearchCollectionExtensionInterface;
use ApiPlatform\Metadata\Operation;

final class BillSearchExtension implements RequestBodySearchCollectionExtensionInterface
{
    public function applyToCollection(array $requestBody, string $resourceClass, ?Operation $operation = null, array $context = []): array
    {
        return $context['bill_search_body'];
    }
}
