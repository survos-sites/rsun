<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand('app:download', 'Download Richmond Sunlight bulk datasets into data directory')]
final class DownloadRichmondSunlightRawFilesCommand
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Target data directory (relative to project root)')]
        string $dataDir = 'data',
        #[Option('Overwrite existing files')]
        bool $force = false,
        #[Option('Optional PCRE to filter download URLs')]
        ?string $filter = null,
        #[Option('First session year')]
        int $from = 2023,
        #[Option('Last session year (defaults to current year)')]
        ?int $to = null,
    ): int {
        $to ??= (int) date('Y');
        if ($from < 2006 || $to < $from || $to > (int) date('Y') + 1) {
            $io->error('Invalid session year range.');
            return Command::INVALID;
        }
        if ($filter !== null && @preg_match($filter, '') === false) {
            $io->error('Invalid filter regex.');
            return Command::INVALID;
        }
        $absoluteDataDir = $this->toAbsoluteDataDir($dataDir);
        $this->filesystem->mkdir($absoluteDataDir);
        $io->title('Richmond Sunlight downloads');
        $downloadUrls = [];
        for ($year = $from; $year <= $to; ++$year) {
            $downloadUrls[] = sprintf('https://downloads.richmondsunlight.com/bills-%d.jsonl', $year);
        }

        if ($filter !== null) {
            $filtered = [];
            foreach ($downloadUrls as $url) {
                $match = @preg_match($filter, $url);
                if ($match === false) {
                    $io->error(sprintf('Invalid filter regex: %s', $filter));

                    return Command::FAILURE;
                }

                if ($match === 1) {
                    $filtered[] = $url;
                }
            }
            $downloadUrls = $filtered;
        }

        if ($downloadUrls === []) {
            $io->warning('No download URLs found.');

            return Command::SUCCESS;
        }

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        $io->text(sprintf('Found %d files.', count($downloadUrls)));
        $io->progressStart(count($downloadUrls));

        foreach ($downloadUrls as $url) {
            $targetPath = $this->getTargetPath($absoluteDataDir, $url);

            if ($this->filesystem->exists($targetPath) && !$force) {
                ++$skipped;
                $io->progressAdvance();
                continue;
            }

            $this->filesystem->mkdir(dirname($targetPath));

            try {
                $this->downloadToFile($url, $targetPath);
                ++$downloaded;
            } catch (ExceptionInterface | \RuntimeException $exception) {
                ++$failed;
                $io->newLine();
                $io->error(sprintf('Failed downloading %s: %s', $url, $exception->getMessage()));
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $summary = sprintf('Downloaded %d, skipped %d, failed %d.', $downloaded, $skipped, $failed);
        $failed > 0 ? $io->error($summary) : $io->success($summary);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function toAbsoluteDataDir(string $dataDir): string
    {
        if (str_starts_with($dataDir, '/')) {
            return $dataDir;
        }

        return rtrim($this->projectDir, '/') . '/' . ltrim($dataDir, '/');
    }

    private function getTargetPath(string $absoluteDataDir, string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            throw new \RuntimeException(sprintf('Unusable URL path for %s', $url));
        }

        return rtrim($absoluteDataDir, '/') . '/' . preg_replace('/^bills-/', '', basename($path));
    }

    private function downloadToFile(string $url, string $targetPath): void
    {
        $tmpPath = $targetPath . '.tmp';

        $handle = fopen($tmpPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open file for writing: %s', $tmpPath));
        }

        $succeeded = false;

        try {
            $response = $this->httpClient->request('GET', $url);
            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException(sprintf('HTTP %d', $response->getStatusCode()));
            }

            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }

                $content = $chunk->getContent();
                if ($content === '') {
                    continue;
                }

                if (fwrite($handle, $content) === false) {
                    throw new \RuntimeException(sprintf('Failed writing to %s', $tmpPath));
                }
            }

            $this->filesystem->rename($tmpPath, $targetPath, true);
            $succeeded = true;
        } finally {
            fclose($handle);

            if (!$succeeded && $this->filesystem->exists($tmpPath)) {
                $this->filesystem->remove($tmpPath);
            }
        }
    }
}
