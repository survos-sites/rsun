<?php

declare(strict_types=1);

namespace App\Search;

use App\Service\DuckDbBills;
use Survos\SearchBundle\Adapter\DuckDb\DuckDbConnectionProviderInterface;

final readonly class BillDuckDbConnection implements DuckDbConnectionProviderInterface
{
    public function __construct(private DuckDbBills $bills) {}

    public function name(): string
    {
        return 'bills';
    }

    public function connect(array $options = []): \PDO
    {
        return $this->bills->open();
    }
}
