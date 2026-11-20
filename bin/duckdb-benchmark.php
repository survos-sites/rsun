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
$duck = new DuckDbBills(dirname(__DIR__));
$gateway = $container->get(InstantSearchGateway::class);
$db = $duck->open();
$rows = [];
$queries = ['', 'minimum wage', 'school funding', 'abortion', 'solar energy', 'hb1'];
foreach ($queries as $query) {
    $timings = ['duckdb' => [], 'elasticsearch' => []];
    for ($i = 0; $i < 6; ++$i) {
        $start = hrtime(true);
        $d = $duck->search($query, 2026, db: $db);
        $duckMs = (hrtime(true) - $start) / 1e6;
        $start = hrtime(true);
        $e = $gateway->search([['indexName' => 'app_bill', 'params' => ['query' => $query, 'hitsPerPage' => 24, 'facetFilters' => ['year:2026']]]])['results'][0];
        $esMs = (hrtime(true) - $start) / 1e6;
        if ($i > 0) {
            $timings['duckdb'][] = $duckMs;
            $timings['elasticsearch'][] = $esMs;
        }
    }
    foreach ($timings as &$values) { sort($values); }
    unset($values);
    $duckIds = array_map('intval', array_column(array_slice($d['hits'], 0, 10), 'id'));
    $esIds = array_map('intval', array_column(array_slice($e['hits'], 0, 10), 'id'));
    $rows[] = ['query' => $query, 'year' => 2026, 'duckdbTotal' => $d['total'], 'esTotal' => $e['nbHits'],
        'duckdbMedianMs' => round($timings['duckdb'][2], 2), 'esMedianMs' => round($timings['elasticsearch'][2], 2),
        'top10Overlap' => count(array_intersect($duckIds, $esIds)), 'duckdbTop10' => $duckIds, 'esTop10' => $esIds];
}
echo json_encode(['snapshot' => $duck->metadata(), 'method' => 'One warmup + five measured sequential runs; median; 24 hits; 2026 filter; persistent DuckDB connection; Elasticsearch gateway includes facets, highlights and larger payloads. This compares current implementations, not equivalent engine workloads.', 'queries' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
