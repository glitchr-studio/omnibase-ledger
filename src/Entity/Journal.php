<?php

namespace Base\Ledger\Entity;

use Base\Ledger\Repository\JournalRepository;
use Doctrine\ORM\Mapping as ORM;

/** A book's journal: BQ (bank), VT (sales: rent notices), AC (purchases), OD (miscellaneous), AN (opening balances). */
#[ORM\Entity(repositoryClass: JournalRepository::class)]
#[ORM\Table(name: 'ledger_journal')]
#[ORM\UniqueConstraint(name: 'ledger_journal_code', fields: ['book', 'code'])]
class Journal
{
    public const BANK = 'BQ';
    public const SALES = 'VT';
    public const PURCHASES = 'AC';
    public const MISCELLANEOUS = 'OD';
    public const OPENING = 'AN';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Book $book;

    #[ORM\Column(length: 8)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $label;

    public function __construct(Book $book, string $code, string $label)
    {
        $this->book = $book;
        $this->code = strtoupper($code);
        $this->label = $label;
    }

    public function __toString(): string
    {
        return $this->code.' '.$this->label;
    }

    public function getId(): ?int { return $this->id; }
    public function getBook(): Book { return $this->book; }
    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
}
