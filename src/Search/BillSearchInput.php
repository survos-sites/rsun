<?php

declare(strict_types=1);
namespace App\Search;

use Symfony\Component\Validator\Constraints as Assert;

final class BillSearchInput
{
    #[Assert\Length(max: 500)]
    public string $query = '';
    #[Assert\Range(min: 1900, max: 2100)]
    public int $year = 2026;
    #[Assert\PositiveOrZero]
    public int $billId = 0;
    #[Assert\Range(min: 1, max: 50)]
    public int $limit = 10;
    #[Assert\Range(min: 0, max: 9950)]
    public int $offset = 0;
    #[Assert\Choice(choices: ['hybrid', 'fulltext', 'summaries'])]
    public string $mode = 'hybrid';
    #[Assert\Range(min: 0, max: 1)]
    public float $minimumSimilarity = 0.65;
    #[Assert\Choice(choices: ['passed', 'failed', 'vetoed', 'incorporated'])]
    public ?string $outcome = null;
    #[Assert\Length(max: 200)]
    public ?string $status = null;
    #[Assert\Choice(choices: ['house', 'senate'])]
    public ?string $chamber = null;
    #[Assert\Length(max: 200)]
    public ?string $patron = null;
    #[Assert\Choice(choices: ['D', 'R', 'I'])]
    public ?string $party = null;
}
