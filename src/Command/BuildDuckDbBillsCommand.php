<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DuckDbBills;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:duckdb:build', 'Build and publish a local DuckDB bill snapshot with full-text search')]
final readonly class BuildDuckDbBillsCommand
{
    public function __construct(private DuckDbBills $bills) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $result = $this->bills->build();
        $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
