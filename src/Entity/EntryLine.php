<?php

namespace Base\Ledger\Entity;

use Base\Ledger\Repository\EntryLineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One line of an entry: an amount on the debit or the credit side of an
 * account, maybe for a party. Lettering ties lines of one account and party
 * whose debits and credits cancel out (a rent notice and its payment): they
 * share a $letter and are no longer open items. Only the lettering fields
 * change once the entry is validated.
 */
#[ORM\Entity(repositoryClass: EntryLineRepository::class)]
#[ORM\Table(name: 'ledger_entry_line')]
#[ORM\Index(name: 'ledger_entry_line_letter', fields: ['letter'])]
class EntryLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entry::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Entry $entry;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Party::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Party $party;

    /** Minor units (cents), never negative; one of debit and credit is zero. */
    #[ORM\Column]
    private int $debit;

    #[ORM\Column]
    private int $credit;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $letter = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $letteredOn = null;

    public function __construct(Entry $entry, Account $account, int $debit, int $credit, ?Party $party = null, ?string $label = null)
    {
        $this->entry = $entry;
        $this->account = $account;
        $this->debit = $debit;
        $this->credit = $credit;
        $this->party = $party;
        $this->label = null === $label ? null : mb_substr($label, 0, 255);
        $entry->addLine($this);
    }

    public function __toString(): string
    {
        return sprintf('%s %s %s', $this->account->getNumber(), $this->entry->getLabel(), $this->debit > 0 ? 'D '.$this->debit : 'C '.$this->credit);
    }

    public function getId(): ?int { return $this->id; }
    public function getEntry(): Entry { return $this->entry; }
    public function getBook(): Book { return $this->entry->getBook(); }
    public function getAccount(): Account { return $this->account; }
    public function getParty(): ?Party { return $this->party; }
    public function getDebit(): int { return $this->debit; }
    public function getCredit(): int { return $this->credit; }
    public function getLabel(): ?string { return $this->label; }
    public function getLetter(): ?string { return $this->letter; }
    public function getLetteredOn(): ?\DateTimeImmutable { return $this->letteredOn; }
    public function isLettered(): bool { return null !== $this->letter; }

    /** Debit minus credit: positive when the line is owed to the book (a rent notice on 411). */
    public function getNet(): int
    {
        return $this->debit - $this->credit;
    }

    /** By Lettering only: it checks the lines cancel out. */
    public function setLetter(?string $letter, ?\DateTimeImmutable $on = null): self
    {
        $this->letter = $letter;
        $this->letteredOn = null === $letter ? null : ($on ?? new \DateTimeImmutable('today'));

        return $this;
    }
}
