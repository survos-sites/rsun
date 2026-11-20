<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\BillRetrieval;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

final readonly class BillTools
{
    public function __construct(private BillRetrieval $search, private \App\State\BillSearchProvider $api) {}

    /** Delegate site chat to the same provider as the API Platform find_bills MCP operation. */
    public function find(string $query, int $year = 2026, int $billId = 0, int $limit = 10, int $offset = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null, ?string $party = null, string $mode = 'hybrid', float $minimumSimilarity = 0.65): array
    {
        return get_object_vars($this->api->search(compact('query','year','billId','limit','offset','outcome','status','chamber','patron','party','mode','minimumSimilarity')));
    }

    /** Read the complete section returned by find_bills, including marked additions/deletions. */
    #[McpTool(name: 'read_bill_section', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function read(string $id): array
    {
        return $this->search->section($id);
    }
    /** Read a bill's title, summary, patron, current archive text and historical version references. */
    #[McpTool(name: 'read_bill', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function bill(int $id): array
    {
        return $this->search->bill($id);
    }
    /** Title-weighted keyword discovery over bill titles and summaries, with aliases for vaping, tobacco, youth, guns and immigration. OR combines alternatives; this is not embedding search. Use find_bills for semantic section search. limit 1–50, offset 0–9950; optional outcome (passed, failed, vetoed, incorporated), exact status, chamber (house/senate), party (D/R/I), patron substring. Budget line items are not separately indexed. Read the cited bill before answering details. */
    #[McpTool(name: 'find_bill_summaries', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function summaries(string $query, int $year = 2026, int $billId = 0, int $limit = 20, int $offset = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null, ?string $party = null): array
    {
        return get_object_vars($this->api->search(compact('query','year','billId','limit','offset','outcome','status','chamber','patron','party') + ['mode'=>'summaries']));
    }
    /** Exact distinct bill counts and outcome/chamber/party facets from the canonical API search. Same filters and modes as find_bills: hybrid (default, keyword OR cosine threshold), fulltext, summaries. Empty query counts all filtered catalog bills. Counts apply to the indexed snapshot and query, not an exhaustive legal topic inventory. */
    #[McpTool(name: 'count_bills', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function counts(string $query = '', int $year = 2026, int $billId = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null, ?string $party = null, string $mode = 'hybrid', float $minimumSimilarity = 0.65): array
    {
        return get_object_vars($this->api->search(compact('query','year','billId','outcome','status','chamber','patron','party','mode','minimumSimilarity') + ['limit'=>1]));
    }
}
