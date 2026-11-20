<?php

declare(strict_types=1);
namespace App\Chat\Tool;
final readonly class FindBillSummaries
{
    public function __construct(private \App\Mcp\BillTools $tools) {}
    public function __invoke(string $query, int $year = 2026, int $billId = 0): array
    {
        return $this->tools->summaries($query, $year, $billId);
    }
}
