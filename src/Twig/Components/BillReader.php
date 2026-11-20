<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Service\BillReader as Reader;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class BillReader
{
    public array $document;

    public function __construct(private Reader $reader) {}

    public function mount(string $html, string $id = 'bill'): void
    {
        $this->document = $this->reader->render($html, $id);
    }
}
