<?php

declare(strict_types=1);

use App\Kernel;
use App\Service\DuckDbBills;
use Survos\SearchBundle\Http\InstantSearchGateway;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$gateway = $container->get(InstantSearchGateway::class);
$rows = [];
foreach (['', 'minimum wage', 'school funding', 'abortion', 'solar energy', 'hb1'] as $query) {
    $timings = [];
    for ($i = 0; $i < 6; ++$i) {
        $start = hrtime(true);
        $result = $gateway->search([['indexName' => 'app_bill', 'params' => ['query' => $query, 'hitsPerPage' => 24, 'facetFilters' => ['year:2026']]]])['results'][0];
        if ($i > 0) {
            $timings[] = (hrtime(true) - $start) / 1e6;
        }
    }
    sort($timings);
    $rows[] = ['query' => $query, 'year' => 2026, 'total' => $result['nbHits'],
        'medianMs' => round($timings[2], 2), 'facets' => $result['facets'],
        'top10' => array_column(array_slice($result['hits'], 0, 10), 'id')];
}
echo json_encode([
    'snapshot' => $container->get(DuckDbBills::class)->metadata(),
    'method' => 'SearchBundle InstantSearch gateway, DuckDB adapter; one warmup + five measured runs, median; 24 hits; 2026 filter; six facets; fresh read-only connection per search. No Elasticsearch comparison.',
    'queries' => $rows,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
$kernel->shutdown();
