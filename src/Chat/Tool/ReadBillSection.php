<?php

declare(strict_types=1);

namespace App\Chat\Tool;

final readonly class ReadBillSection
{
    public function __construct(private \App\Mcp\BillTools $tools) {}
    public function __invoke(string $id): array
    {
        return $this->tools->read($id);
    }
}
