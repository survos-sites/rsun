<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\BillRetrieval;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

final readonly class BillTools
{
    public function __construct(private BillRetrieval $search) {}

    /** Search available bill sections. Use concise terms; OR combines alternatives. Results are evidence, not a statement of current law. */
    #[McpTool(name: 'find_bills', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function find(string $query, int $year = 2026, int $billId = 0): array
    {
        return $this->search->find($query, $year, $billId);
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
    /** Discover bills using their existing titles and summaries; read the bill before answering details. */
    #[McpTool(name: 'find_bill_summaries', annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false))]
    public function summaries(string $query, int $year = 2026, int $billId = 0): array
    {
        return $this->search->summaries($query, $year, $billId);
    }
}
