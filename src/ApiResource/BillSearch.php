<?php

declare(strict_types=1);
namespace App\ApiResource;

use ApiPlatform\Metadata\{ApiProperty, ApiResource, Get, McpTool, QueryParameter};
use App\Search\BillSearchInput;
use App\State\BillSearchProvider;

#[ApiResource(
    formats: ['json' => ['application/json'], 'jsonld' => ['application/ld+json']],
    operations: [new Get(uriTemplate: '/bills/search', provider: BillSearchProvider::class, parameters: [
        'query' => new QueryParameter(schema: ['type'=>'string', 'maxLength'=>500]),
        'year' => new QueryParameter(schema: ['type'=>'integer', 'default'=>2026]),
        'billId' => new QueryParameter(schema: ['type'=>'integer', 'default'=>0]),
        'mode' => new QueryParameter(schema: ['type'=>'string', 'enum'=>['hybrid','fulltext','summaries'], 'default'=>'hybrid']),
        'minimumSimilarity' => new QueryParameter(schema: ['type'=>'number', 'minimum'=>0, 'maximum'=>1, 'default'=>0.65]),
        'limit' => new QueryParameter(schema: ['type'=>'integer', 'minimum'=>1, 'maximum'=>50, 'default'=>10]),
        'offset' => new QueryParameter(schema: ['type'=>'integer', 'minimum'=>0, 'maximum'=>9950, 'default'=>0]),
        'outcome' => new QueryParameter(schema: ['type'=>'string', 'enum'=>['passed','failed','vetoed','incorporated']]),
        'status' => new QueryParameter(schema: ['type'=>'string']),
        'chamber' => new QueryParameter(schema: ['type'=>'string', 'enum'=>['house','senate']]),
        'patron' => new QueryParameter(schema: ['type'=>'string']),
        'party' => new QueryParameter(schema: ['type'=>'string', 'enum'=>['D','R','I']]),
    ])],
    mcp: ['find_bills' => new McpTool(
        name: 'find_bills',
        description: 'Search Virginia bills by meaning and keywords. One result per bill with a matching passage and citation. Modes hybrid (keyword OR cosine similarity at minimumSimilarity, default 0.65), fulltext, summaries. Exact distinct-bill total and outcome/chamber/party counts for that query in the indexed snapshot, not a complete topic inventory. Empty query lists/counts all filtered catalog bills. Filters year, billId, outcome, status, chamber, patron substring, party. limit 1–50, offset 0–9950. Budget line items and missing historical bodies are not covered.',
        provider: BillSearchProvider::class, input: BillSearchInput::class,
        annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
    )],
)]
final class BillSearch
{
    #[ApiProperty(identifier: true)]
    public string $id = 'search';
    public string $unit = 'bills';
    public string $engine = 'api-platform/elasticsearch';
    public int $total = 0;
    public int $returnedCount = 0;
    public int $offset = 0;
    public int $limit = 10;
    public bool $hasMore = false;
    public array $criteria = [];
    public array $facets = [];
    public array $coverage = [];
    public array $bills = [];
    public string $countBasis = 'Exact distinct bill IDs matching this query in the indexed snapshot. Semantic matches use the stated cosine threshold; this is not an exhaustive legal topic inventory. Budget line items are not separately indexed.';
}
