<?php

declare(strict_types=1);

namespace App\Chat\Tool;

final readonly class FindBills
{
    public function __construct(private \App\Mcp\BillTools $tools) {}
    public function __invoke(string $query, int $year = 2026, int $billId = 0, int $limit = 10, int $offset = 0,
        ?string $outcome = null, ?string $status = null, ?string $chamber = null, ?string $patron = null): array
    {
        return $this->tools->find($query, $year, $billId, $limit, $offset, $outcome, $status, $chamber, $patron);
    }
}
