<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\DownloadRichmondSunlightRawFilesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DownloadBillsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/rsun-download-'.bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testExistingSnapshotIsSkippedUnlessForced(): void
    {
        file_put_contents($this->directory.'/2026.jsonl', 'old');
        $client = new MockHttpClient(function (string $method, string $url): MockResponse {
            self::assertSame('https://downloads.richmondsunlight.com/bills-2026.jsonl', $url);
            return new MockResponse('{"id":1}');
        });
        $command = new DownloadRichmondSunlightRawFilesCommand($client, new Filesystem(), $this->directory);
        $io = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
        self::assertSame(0, $command($io, dataDir: $this->directory, from: 2026, to: 2026));
        self::assertSame(0, $client->getRequestsCount());
        self::assertSame('old', file_get_contents($this->directory.'/2026.jsonl'));
        self::assertSame(0, $command($io, dataDir: $this->directory, force: true, from: 2026, to: 2026));
        self::assertSame(1, $client->getRequestsCount());
        self::assertSame('{"id":1}', file_get_contents($this->directory.'/2026.jsonl'));
    }

    public function testFailedRefreshPreservesExistingSnapshot(): void
    {
        file_put_contents($this->directory.'/2026.jsonl', 'old');
        $client = new MockHttpClient(new MockResponse('unavailable', ['http_code' => 503]));
        $command = new DownloadRichmondSunlightRawFilesCommand($client, new Filesystem(), $this->directory);
        $io = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
        self::assertSame(1, $command($io, dataDir: $this->directory, force: true, from: 2026, to: 2026));
        self::assertSame('old', file_get_contents($this->directory.'/2026.jsonl'));
        self::assertFileDoesNotExist($this->directory.'/2026.jsonl.tmp');
    }

    public function testInvalidRangeAndFilterDoNotRequestData(): void
    {
        $client = new MockHttpClient();
        $command = new DownloadRichmondSunlightRawFilesCommand($client, new Filesystem(), $this->directory);
        $io = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
        self::assertSame(2, $command($io, from: 2026, to: 2025));
        self::assertSame(2, $command($io, filter: '[', from: 2026, to: 2026));
        self::assertSame(0, $client->getRequestsCount());
    }
}
