<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'bill_section_position', columns: ['version_id', 'position'])]
class BillSection
{
    #[ORM\Id, ORM\Column(length: 64)]
    public string $id;
    #[ORM\ManyToOne(targetEntity: BillVersion::class), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BillVersion $version;
    #[ORM\Column]
    public int $position;
    #[ORM\Column(length: 64)]
    public string $anchor;
    #[ORM\Column(type: 'text')]
    public string $heading;
    #[ORM\Column(type: 'text')]
    public string $html;
    #[ORM\Column(type: 'text')]
    public string $text;
    #[ORM\Column(type: 'text')]
    public string $proposedText;
    /** Inline marked changes, not independent amendment documents. */
    #[ORM\Column(type: 'json')]
    public array $changes = [];
}
