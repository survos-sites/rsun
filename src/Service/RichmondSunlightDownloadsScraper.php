<?php

declare(strict_types=1);

namespace App\Service;

final class RichmondSunlightDownloadsScraper
{
    public const string DOWNLOADS_PAGE_URL = 'https://www.richmondsunlight.com/downloads/';

    private const string DOWNLOADS_HOST = 'downloads.richmondsunlight.com';

    /**
     * @return list<string>
     */
    public function extractDownloadUrls(string $html): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $urls = [];
        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = trim($link->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $absoluteUrl = $this->normalizeUrl($href);
            if ($absoluteUrl === null) {
                continue;
            }

            if (parse_url($absoluteUrl, PHP_URL_HOST) !== self::DOWNLOADS_HOST) {
                continue;
            }

            $urls[$absoluteUrl] = true;
        }

        $downloadUrls = array_keys($urls);
        sort($downloadUrls);

        /** @var list<string> $downloadUrls */
        return $downloadUrls;
    }

    private function normalizeUrl(string $href): ?string
    {
        if (str_starts_with($href, 'https://')) {
            return $href;
        }

        if (str_starts_with($href, 'http://')) {
            return 'https://' . substr($href, 7);
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        // We ignore relative URLs — the downloads page uses absolute URLs.
        return null;
    }
}
