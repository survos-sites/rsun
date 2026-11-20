<?php

declare(strict_types=1);

namespace App\Chat\Tool;

final readonly class FindBills
{
    public function __construct(private \App\Mcp\BillTools $tools) {}
    public function __invoke(string $query, int $year = 2026, int $billId = 0): array
    {
        return $this->tools->find($query, $year, $billId);
    }
}
