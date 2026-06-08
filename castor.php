<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

#[AsTask(name: 'bills:load', description: 'Download and import Richmond Sunlight bill JSONL files')]
function bills_load(bool $fetch = false, int $from = 2023, int $to = 2026): void
{
    $io = io();
    $io->title('Load Richmond Sunlight bills');

    run('php bin/console doctrine:schema:update --force');

    if ($fetch) {
        run('php bin/console app:download --force');
    }

    for ($year = $from; $year <= $to; ++$year) {
        $file = sprintf('data/%d.jsonl', $year);
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Missing %s. Re-run with --fetch or download the file first.', $file));
        }

        $reset = $year === $from ? ' --reset' : '';
        run(sprintf('php bin/console import:entities %s %s%s', escapeshellarg('App\Entity\Bill'), escapeshellarg($file), $reset));
    }

    $io->success(sprintf('Imported bills from %d through %d.', $from, $to));
}
