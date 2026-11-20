<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Service\DuckDbBills;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class DuckDbBillsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_duckdb')) {
            self::markTestSkipped('Native DuckDB PDO is required for the local experiment.');
        }
        $this->root = sys_get_temp_dir().'/rsun-duckdb-'.bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->root.'/data');
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            (new Filesystem())->remove($this->root);
        }
    }

    public function testSnapshotDeduplicatesFiltersAndPreservesVersions(): void
    {
        $one = ['id' => 1, 'year' => 2026, 'number' => 'hb1', 'chamber' => 'house', 'catch_line' => 'Minimum wage increase', 'summary' => '<p>Raise the minimum wage.</p>', 'text' => [['number' => 'HB1ER', 'date' => '2026-03-11']]];
        $two = ['id' => 2, 'year' => 2025, 'number' => 'sb1', 'chamber' => 'senate', 'catch_line' => 'Minimum wage study'];
        file_put_contents($this->root.'/data/2026.jsonl', implode("\n", array_map(json_encode(...), [$one, $one, $two])));
        $service = new DuckDbBills($this->root);
        $meta = $service->build();
        self::assertSame(3, $meta['sourceRows']);
        self::assertSame(2, $meta['bills']);
        self::assertSame(1, $meta['versions']);
        self::assertSame($one, $service->bill(1));
        self::assertNull($service->bill(99));
        $found = $service->search('minimum wage', 2026);
        self::assertSame(1, $found['total']);
        self::assertSame(1, (int) $found['hits'][0]['id']);
        self::assertSame(' Raise the minimum wage. ', $found['hits'][0]['summary']);
        self::assertSame(0, $service->search('minimum wage', 2026, 'senate')['total']);
        self::assertSame(0, $service->search("x' OR 1=1 --", 2024)['total']);
        self::assertSame(1, $service->search('', null, '', 999)['page']);
        $db = $service->open();
        $this->expectException(\PDOException::class);
        $db->exec('DELETE FROM bills');
    }

    public function testConflictingDuplicatesDoNotReplacePublishedSnapshot(): void
    {
        $path = $this->root.'/data/2026.jsonl';
        file_put_contents($path, json_encode(['id' => 1, 'year' => 2026, 'catch_line' => 'School funding']));
        $service = new DuckDbBills($this->root);
        $published = $service->build();
        file_put_contents($path, "\n".json_encode(['id' => 1, 'year' => 2026, 'catch_line' => 'Different bill']), FILE_APPEND);
        try {
            $service->build();
            self::fail('Conflicting duplicates must fail.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Conflicting records', $error->getMessage());
        }
        self::assertSame($published['file'], $service->metadata()['file']);
        self::assertSame('School funding', $service->bill(1)['catch_line']);
    }
}
