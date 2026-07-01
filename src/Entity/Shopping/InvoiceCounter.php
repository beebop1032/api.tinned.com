<?php

namespace App\Entity\Shopping;

use Doctrine\ORM\Mapping as ORM;

/**
 * Per-year counter backing the legal sequential invoice numbering. One row per year,
 * incremented under a pessimistic lock so numbers are unique and gap-consistent.
 * Not exposed via the API.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoice_counter')]
class InvoiceCounter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private int $year;

    #[ORM\Column(options: ['default' => 0])]
    private int $lastNumber = 0;

    public function getId(): ?int { return $this->id; }
    public function getYear(): int { return $this->year; }
    public function setYear(int $year): self { $this->year = $year; return $this; }
    public function getLastNumber(): int { return $this->lastNumber; }
    public function setLastNumber(int $lastNumber): self { $this->lastNumber = $lastNumber; return $this; }
}
