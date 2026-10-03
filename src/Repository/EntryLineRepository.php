<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\Account;
use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\EntryLine;
use Base\Ledger\Entity\Party;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EntryLine> */
class EntryLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntryLine::class);
    }

    /**
     * The unlettered lines - what is still owed, either way - of the
     * accounts under $accountPrefix (all when null), maybe one party's,
     * oldest first.
     *
     * @return list<EntryLine>
     */
    public function openItems(Book $book, ?Party $party = null, ?string $accountPrefix = null): array
    {
        $qb = $this->lines($book)
            ->andWhere('l.letter IS NULL')
            ->orderBy('e.date', 'ASC')->addOrderBy('l.id', 'ASC');
        if (null !== $party) {
            $qb->andWhere('l.party = :party')->setParameter('party', $party);
        }
        if (null !== $accountPrefix && '' !== $accountPrefix) {
            $qb->andWhere('a.number LIKE :prefix')->setParameter('prefix', addcslashes(Account::normalize($accountPrefix), '%_').'%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<EntryLine> */
    public function findByLetter(Book $book, string $letter): array
    {
        return $this->lines($book)->andWhere('l.letter = :letter')->setParameter('letter', $letter)
            ->orderBy('l.id', 'ASC')->getQuery()->getResult();
    }

    /** The last letter given in the book: letters run AAA, AAB... ZZZ, AAAA. */
    public function lastLetter(Book $book): ?string
    {
        $row = $this->lines($book)->select('l.letter, LENGTH(l.letter) AS HIDDEN size')
            ->andWhere('l.letter IS NOT NULL')
            ->orderBy('size', 'DESC')->addOrderBy('l.letter', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return $row['letter'] ?? null;
    }

    /**
     * Debit and credit totals per account over [$from, $to] (either open).
     *
     * @return list<array{number: string, label: string, type: mixed, debit: int, credit: int}>
     */
    public function totalsByAccount(Book $book, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, bool $validatedOnly = false): array
    {
        $qb = $this->period($this->lines($book), $from, $to, $validatedOnly)
            ->select('a.number AS number, a.label AS label, a.type AS type, SUM(l.debit) AS debit, SUM(l.credit) AS credit')
            ->groupBy('a.id, a.number, a.label, a.type')
            ->orderBy('a.number', 'ASC');

        return array_map(fn (array $row) => ['debit' => (int) $row['debit'], 'credit' => (int) $row['credit']] + $row, $qb->getQuery()->getArrayResult());
    }

    /**
     * The lines of the accounts under $accountPrefix over [$from, $to], by
     * account then date: the general ledger's rows.
     *
     * @return list<EntryLine>
     */
    public function forLedger(Book $book, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?string $accountPrefix = null): array
    {
        $qb = $this->period($this->lines($book), $from, $to, false)
            ->orderBy('a.number', 'ASC')->addOrderBy('e.date', 'ASC')->addOrderBy('l.id', 'ASC');
        if (null !== $accountPrefix && '' !== $accountPrefix) {
            $qb->andWhere('a.number LIKE :prefix')->setParameter('prefix', addcslashes(Account::normalize($accountPrefix), '%_').'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Debit and credit totals of the accounts under $accountPrefix, all
     * lines or only the unlettered ones.
     *
     * @return array{debit: int, credit: int}
     */
    public function sumUnder(Book $book, string $accountPrefix, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, bool $openOnly = false): array
    {
        $qb = $this->period($this->lines($book), $from, $to, false)
            ->select('COALESCE(SUM(l.debit), 0) AS debit, COALESCE(SUM(l.credit), 0) AS credit')
            ->andWhere('a.number LIKE :prefix')->setParameter('prefix', addcslashes(Account::normalize($accountPrefix), '%_').'%');
        if ($openOnly) {
            $qb->andWhere('l.letter IS NULL');
        }
        $row = $qb->getQuery()->getSingleResult();

        return ['debit' => (int) $row['debit'], 'credit' => (int) $row['credit']];
    }

    /** An account's balance (debit minus credit), all dates or up to $to. */
    public function balance(Account $account, ?\DateTimeImmutable $to = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(l.debit), 0) AS debit, COALESCE(SUM(l.credit), 0) AS credit')
            ->join('l.entry', 'e')
            ->andWhere('l.account = :account')->setParameter('account', $account);
        if (null !== $to) {
            $qb->andWhere('e.date <= :to')->setParameter('to', $to->setTime(0, 0), 'date_immutable');
        }
        // DQL has no arithmetic between aggregates: the difference is made here.
        $row = $qb->getQuery()->getSingleResult();

        return (int) $row['debit'] - (int) $row['credit'];
    }

    private function lines(Book $book): QueryBuilder
    {
        return $this->createQueryBuilder('l')
            ->join('l.entry', 'e')->addSelect('e')
            ->join('l.account', 'a')->addSelect('a')
            ->andWhere('e.book = :book')->setParameter('book', $book);
    }

    private function period(QueryBuilder $qb, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $validatedOnly): QueryBuilder
    {
        if (null !== $from) {
            $qb->andWhere('e.date >= :from')->setParameter('from', $from->setTime(0, 0), 'date_immutable');
        }
        if (null !== $to) {
            $qb->andWhere('e.date <= :to')->setParameter('to', $to->setTime(0, 0), 'date_immutable');
        }
        if ($validatedOnly) {
            $qb->andWhere('e.validatedAt IS NOT NULL');
        }

        return $qb;
    }
}
