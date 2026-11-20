<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Service\DuckDbBills;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class BillDuckDbHttpTest extends WebTestCase
{
    public function testExperimentalSearchAndDoctrineDetail(): void
    {
        if (!extension_loaded('pdo_duckdb')) {
            self::markTestSkipped('Native DuckDB PDO required.');
        }
        $root = sys_get_temp_dir().'/rsun-http-'.bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($root.'/data');
        $bills = [
            ['id' => 900001, 'year' => 2026, 'number' => 'hb1', 'chamber' => 'house', 'catch_line' => 'Minimum wage increase', 'patron_name_formatted' => 'Patron A', 'summary' => '<p>Raise wages</p>', 'status' => 'passed'],
            ['id' => 900002, 'year' => 2026, 'number' => 'sb1', 'chamber' => 'senate', 'catch_line' => 'Minimum wage study', 'status' => 'passed'],
            ['id' => 900003, 'year' => 2025, 'number' => 'hb2', 'chamber' => 'house', 'catch_line' => 'School funding', 'status' => 'passed'],
        ];
        try {
            file_put_contents($root.'/data/2026.jsonl', implode("\n", array_map(json_encode(...), $bills)));
            $duck = new DuckDbBills($root);
            $duck->build();
            $client = self::createClient();
            $client->disableReboot();
            self::getContainer()->set(DuckDbBills::class, $duck);
            $request = static function (array $params = [], string $index = 'app_bill') use ($client): array {
                $client->jsonRequest('POST', '/instant-search', ['requests' => [['indexName' => $index, 'params' => $params]]]);
                self::assertResponseIsSuccessful();

                return json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['results'][0];
            };
            $result = $request(['query' => 'minimum wage', 'hitsPerPage' => 1, 'facetFilters' => ['year:2026']]);
            self::assertSame(2, $result['nbHits']);
            self::assertCount(1, $result['hits']);
            self::assertArrayHasKey('catchLine', $result['hits'][0]);
            self::assertSame(['house' => 1, 'senate' => 1], $result['facets']['chamber']);
            $second = $request(['query' => 'minimum wage', 'hitsPerPage' => 1, 'page' => 1, 'facetFilters' => ['year:2026']]);
            self::assertNotSame($result['hits'][0]['id'], $second['hits'][0]['id']);
            $filtered = $request(['facetFilters' => ['chamber:house', 'year:2026']]);
            self::assertSame(1, $filtered['nbHits']);
            self::assertSame(['house' => 1, 'senate' => 1], $filtered['facets']['chamber']);
            self::assertEquals([2025 => 1, 2026 => 1], $filtered['facets']['year']);
            self::assertSame(2025, $request([], 'app_bill::year_asc')['hits'][0]['year']);
            self::assertSame(1, $request(['query' => 'hb1'])['nbHits']);
            self::assertSame(0, $request(['query' => 'unfindableword'])['nbHits']);
            $facetOnly = $request(['query' => 'minimum wage', 'hitsPerPage' => 0, 'facets' => 'year']);
            self::assertSame(['year'], array_keys($facetOnly['facets']));
            self::assertSame([], $facetOnly['hits']);
            self::assertSame([], $request(['facets' => []])['facets']);
            self::assertCount(6, $request(['facets' => ['*']])['facets']);
            $client->request('GET', '/bills/search');
            self::assertResponseIsSuccessful();
            foreach (['duckdb', 'postgres', 'es'] as $backend) {
                $client->request('GET', '/bills/search/'.$backend);
                self::assertResponseIsSuccessful();
                self::assertSelectorExists('nav[aria-label="Search backend"]');
            }
            $client->jsonRequest('POST', '/bills/search/duckdb/query', ['requests' => [['indexName' => 'app_bill', 'params' => ['query' => 'hb1']]]]);
            self::assertResponseIsSuccessful();
            self::assertSame(1, json_decode($client->getResponse()->getContent(), true)['results'][0]['nbHits']);
            foreach (['duckdb' => 'app_bill_es', 'postgres' => 'app_bill', 'es' => 'app_bill_pg'] as $backend => $wrongName) {
                $client->jsonRequest('POST', '/bills/search/'.$backend.'/query', ['requests' => [['indexName' => $wrongName]]]);
                self::assertResponseStatusCodeSame(400);
            }
            $client->request('GET', '/api/search/bills/duckdb?q=minimum%20wage&limit=1&filter[year]=2026');
            self::assertResponseIsSuccessful();
            $api = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(2, $api['total']);
            self::assertSame(1, $api['page']);
            self::assertCount(1, $api['hits']);
            self::assertStringContainsString('<mark>', $api['hits'][0]['_highlightResult']['catchLine']['value']);
            self::assertSame(['house' => 1, 'senate' => 1], $api['facets']['chamber']);
            $client->request('GET', '/api/search/bills/duckdb?q=minimum&filter[chamber][]=house&filter[chamber][]=senate&min[year]=2026');
            self::assertResponseIsSuccessful();
            self::assertSame(2, json_decode($client->getResponse()->getContent(), true)['total']);
            foreach (['limit=101', 'page=0', 'q[]=bad', 'filter[unknown]=x', 'sort=bogus', 'backend=es', 'min[year]=oops'] as $invalid) {
                $client->request('GET', '/api/search/bills/duckdb?'.$invalid);
                self::assertContains($client->getResponse()->getStatusCode(), [400, 422], $invalid);
            }
            $bill = new \App\Entity\Bill();
            $bill->id = 900001;
            $bill->number = 'hb1';
            $bill->year = 2026;
            $bill->catchLine = 'Minimum wage increase';
            $repository = $this->createStub(\App\Repository\BillRepository::class);
            $repository->method('find')->willReturnCallback(static fn ($id) => 900001 === $id ? $bill : null);
            self::getContainer()->set(\App\Repository\BillRepository::class, $repository);
            $client->request('GET', '/bills/900001');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h2.mb-0', 'Minimum wage increase');
            $client->request('GET', '/bills/999999');
            self::assertResponseStatusCodeSame(404);
            $client->request('GET', '/bills/duckdb');
            self::assertResponseRedirects('/bills/search');
        } finally {
            self::ensureKernelShutdown();
            (new Filesystem())->remove($root);
        }
    }
}
