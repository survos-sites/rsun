<?php

declare(strict_types=1);
namespace App\Tests\Service;

use App\Twig\ChatMarkdownExtension;
use PHPUnit\Framework\TestCase;

final class ChatMarkdownTest extends TestCase
{
    public function testFormatsAnswerAndKeepsRelativeCitations(): void
    {
        $html=(new ChatMarkdownExtension())->render("## Tobacco taxes\n\n* **SB 712** — [Read section](/bills/sections/example)\n* HB 1449\n");
        self::assertStringContainsString('<h2>Tobacco taxes</h2>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<strong>SB 712</strong>', $html);
        self::assertStringContainsString('href="/bills/sections/example"', $html);
    }

    public function testUntrustedAnswerCannotInjectActiveHtml(): void
    {
        $html=(new ChatMarkdownExtension())->render('<script>alert(1)</script><img src=x onerror=alert(1)> [bad](javascript:alert%281%29) ![tracker](https://example.com/tracker)');
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('onerror', $html);
        self::assertStringNotContainsString('href="javascript:', $html);
    }
}
