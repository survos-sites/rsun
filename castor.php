<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

#[AsTask(name: 'bills:load', description: 'Download and import Richmond Sunlight bill JSONL files')]
function bills_load(bool $fetch = false, int $from = 2023, ?int $to = null): void
{
    $to ??= (int) date('Y');
    if ($from < 2006 || $to < $from || $to > (int) date('Y') + 1) {
        throw new InvalidArgumentException('Invalid session year range.');
    }
    $io = io();
    $io->title('Load Richmond Sunlight bills');

    run('php bin/console doctrine:schema:validate', throwOnFailure: true);

    if ($fetch) {
        run(sprintf('php bin/console app:download --force --from=%d --to=%d', $from, $to), throwOnFailure: true);
    }

    for ($year = $from; $year <= $to; ++$year) {
        $file = sprintf('data/%d.jsonl', $year);
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Missing %s. Re-run with --fetch or download the file first.', $file));
        }

        run(sprintf('php bin/console import:entities %s %s --batch=1', escapeshellarg('App\Entity\Bill'), escapeshellarg($file)), throwOnFailure: true);
    }

    $io->success(sprintf('Imported bills from %d through %d.', $from, $to));
}
