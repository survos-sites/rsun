<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'bill_version_identity', columns: ['bill_id', 'code'])]
class BillVersion
{
    #[ORM\Id, ORM\Column(length: 64)]
    public string $id;
    #[ORM\ManyToOne(targetEntity: Bill::class), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Bill $bill;
    #[ORM\Column(length: 128)]
    public string $code;
    #[ORM\Column(length: 10, nullable: true)]
    public ?string $sourceDate = null;
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $sourceUrl = null;
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $sourceHtml = null;
    #[ORM\Column(length: 64, nullable: true)]
    public ?string $sourceHash = null;
    #[ORM\Column(type: 'json')]
    public array $sourceMetadata = [];
}
