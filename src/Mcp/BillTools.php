<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\BillRetrieval;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

final readonly class BillTools
{
    public function __construct(private BillRetrieval $search) {}

    /** Hybrid BM25 + semantic section search (2026 indexed). Use natural language. Code sections are grouped with distinct bill/version citations and short excerpts; read_bill_section returns complete text. limit 1–50, offset 0–200 page a bounded ranked candidate pool, not an exhaustive inventory. Optional outcome (passed, failed, vetoed, incorporated), exact status, chamber (house/senate), patron substring filters. Budget line items are not separately indexed; no hits does not mean no budget provision. Results are evidence, not current law. */
    #[McpTool(name: 'find_bills', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function find(string $query, int $year = 2026, int $billId = 0, int $limit = 10, int $offset = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null): array
    {
        return $this->search->find($query, $year, $billId, $limit, $offset, $outcome, $status, $chamber, $patron);
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
    /** Title-weighted keyword discovery over bill titles and summaries, with aliases for vaping/e-cigarettes and tobacco. OR combines alternatives; this is not embedding search. Use find_bills for semantic section search. limit 1–50, offset 0–200; optional outcome (passed, failed, vetoed, incorporated), exact status, chamber (house/senate), patron substring. Budget line items are not separately indexed. Read the cited bill before answering details. */
    #[McpTool(name: 'find_bill_summaries', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function summaries(string $query, int $year = 2026, int $billId = 0, int $limit = 20, int $offset = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null): array
    {
        return $this->search->summaries($query, $year, $billId, $limit, $offset, $outcome, $status, $chamber, $patron);
    }
}
