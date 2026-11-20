<?php

declare(strict_types=1);
namespace App\State;

use ApiPlatform\Elasticsearch\State\{CollectionProvider, Options};
use ApiPlatform\Metadata\{GetCollection, Operation};
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BillSearch;
use App\Search\{BillHitDenormalizer, BillSearchExtension, BillSearchInput};
use App\Service\BillSearchEngine;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @implements ProviderInterface<BillSearch> */
final readonly class BillSearchProvider implements ProviderInterface
{
    public function __construct(private BillSearchEngine $engine, private ValidatorInterface $validator, private \Symfony\Component\HttpFoundation\RequestStack $requests) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BillSearch
    {
        return $this->search($context['mcp_data'] ?? (($context['request'] ?? null)?->query->all() ?? []));
    }

    /** Shared entry point for HTTP, MCP and site chat; no HTTP loopback. */
    public function search(array $args): BillSearch
    {
        $input = new BillSearchInput();
        foreach ($args as $key => $value) {
            if (!property_exists($input, $key)) throw new BadRequestHttpException('Unknown search parameter: '.$key);
            $property = new \ReflectionProperty($input, $key);
            $type = $property->getType();
            if ($value === null && $type->allowsNull()) { $input->$key = null; continue; }
            if ($type->getName() === 'int') $value = filter_var($value, FILTER_VALIDATE_INT);
            elseif ($type->getName() === 'float') $value = filter_var($value, FILTER_VALIDATE_FLOAT);
            elseif (!is_string($value)) throw new BadRequestHttpException($key.' must be a string.');
            if ($value === false) throw new BadRequestHttpException('Invalid '.$key);
            $input->$key = $value;
        }
        $request = $this->requests->getCurrentRequest();
        if ($request && $request->attributes->get('_route') === 'app_bill_chat') {
            $input->year = $request->query->getInt('year', 2026);
            $input->billId = max(0, $request->query->getInt('bill', 0));
        }
        $input->query = trim($input->query);
        $errors = $this->validator->validate($input);
        if (count($errors)) throw new BadRequestHttpException((string) $errors);
        // Resolve the alias once: counts and results must use the same immutable generation.
        $index = $this->engine->index($input->year);
        $body = $this->engine->body($input);
        $result = new BillSearch();
        $result->criteria = get_object_vars($input);
        $result->limit = $input->limit;
        $result->offset = $input->offset;
        [$result->total, $result->facets] = $this->engine->counts($index, $body['query']);
        $result->coverage = $this->engine->coverage($index);
        $provider = new CollectionProvider($this->engine->client(), new BillHitDenormalizer(), new Pagination(), [new BillSearchExtension()]);
        $items = $provider->provide(new GetCollection(class: BillSearchInput::class, stateOptions: new Options(index: $index), paginationItemsPerPage: $input->limit), context: ['bill_search_body' => $body, 'bill_query' => $input->query]);
        $result->bills = iterator_to_array($items, false);
        $result->returnedCount = count($result->bills);
        $result->hasMore = $input->offset + $result->returnedCount < $result->total;
        return $result;
    }
}
