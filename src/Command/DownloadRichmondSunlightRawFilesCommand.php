<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RichmondSunlightDownloadsScraper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand('app:richmondsunlight:download-raw', 'Download Richmond Sunlight bulk datasets into data directory')]
final class DownloadRichmondSunlightRawFilesCommand
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RichmondSunlightDownloadsScraper $downloadsScraper,
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
    ): int {
        $absoluteDataDir = $this->toAbsoluteDataDir($dataDir);
        $this->filesystem->mkdir($absoluteDataDir);

        $io->title('Richmond Sunlight downloads');

        try {
            $html = $this->httpClient->request('GET', RichmondSunlightDownloadsScraper::DOWNLOADS_PAGE_URL)->getContent();
        } catch (ExceptionInterface $exception) {
            $io->error(sprintf('Failed to fetch downloads index: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        $downloadUrls = $this->downloadsScraper->extractDownloadUrls($html);

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

        $io->success(sprintf('Downloaded %d, skipped %d, failed %d.', $downloaded, $skipped, $failed));

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

        return rtrim($absoluteDataDir, '/') . '/' . ltrim($path, '/');
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
