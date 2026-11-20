<?php

declare(strict_types=1);
namespace App\Chat\Tool;
final readonly class ReadBill
{
    public function __construct(private \App\Mcp\BillTools $tools) {}
    public function __invoke(int $id): array { return $this->tools->bill($id); }
}
