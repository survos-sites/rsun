<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HtmlSanitizer\{HtmlSanitizer, HtmlSanitizerConfig};
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ChatMarkdownExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('chat_markdown', $this->render(...), ['is_safe' => ['html']])];
    }

    public function render(string $text): string
    {
        $markdown = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $sanitizer = new HtmlSanitizer((new HtmlSanitizerConfig())->allowSafeElements()->allowRelativeLinks()->blockElement('img'));
        return $sanitizer->sanitize((string) $markdown->convert($text));
    }
}
