<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RichmondSunlightDownloadsScraper;
use PHPUnit\Framework\TestCase;

final class RichmondSunlightDownloadsScraperTest extends TestCase
{
    public function testExtractDownloadUrlsFiltersAndNormalizes(): void
    {
        $html = <<<'HTML'
<html>
  <body>
    <a href="https://downloads.richmondsunlight.com/bills.csv">Bills CSV</a>
    <a href="https://downloads.richmondsunlight.com/bills.json">Bills JSON</a>
    <a href="https://example.com/not-a-download">Ignore</a>
    <a href="https://downloads.richmondsunlight.com/bills.csv">Duplicate</a>
    <a href="//downloads.richmondsunlight.com/committees.json">Protocol-relative</a>
  </body>
</html>
HTML;

        $scraper = new RichmondSunlightDownloadsScraper();
        $urls = $scraper->extractDownloadUrls($html);

        self::assertSame(
            [
                'https://downloads.richmondsunlight.com/bills.csv',
                'https://downloads.richmondsunlight.com/bills.json',
                'https://downloads.richmondsunlight.com/committees.json',
            ],
            $urls
        );
    }
}
