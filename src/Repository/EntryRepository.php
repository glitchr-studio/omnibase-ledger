<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\Entry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Entry> */
class EntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entry::class);
    }

    public function findOneBySource(Book $book, string $source): ?Entry
    {
        return $this->findOneBy(['book' => $book, 'source' => $source]);
    }

    /** The highest number given with this prefix ("2026-"): numbers are zero-padded, so the greatest string is the last. */
    public function lastNumber(Book $book, string $prefix): ?string
    {
        $number = $this->createQueryBuilder('e')
            ->select('MAX(e.number)')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->andWhere('e.number LIKE :prefix')->setParameter('prefix', addcslashes($prefix, '%_').'%')
            ->getQuery()->getSingleScalarResult();

        return null === $number ? null : (string) $number;
    }

    /** The entry reversing $entry, if one does. */
    public function findReversal(Entry $entry): ?Entry
    {
        return $this->findOneBy(['reversalOf' => $entry]);
    }

    /**
     * The book's entries dated in [$from, $to], with their lines, in the
     * order the FEC lists them (date, then number).
     *
     * @return list<Entry>
     */
    public function forPeriod(Book $book, \DateTimeImmutable $from, \DateTimeImmutable $to, bool $validatedOnly = true): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.lines', 'l')->addSelect('l')
            ->leftJoin('l.account', 'a')->addSelect('a')
            ->leftJoin('l.party', 'p')->addSelect('p')
            ->join('e.journal', 'j')->addSelect('j')
            ->andWhere('e.book = :book')->setParameter('book', $book)
            ->andWhere('e.date >= :from AND e.date <= :to')
            ->setParameter('from', $from->setTime(0, 0), 'date_immutable')
            ->setParameter('to', $to->setTime(0, 0), 'date_immutable')
            ->orderBy('e.date', 'ASC')->addOrderBy('e.number', 'ASC')->addOrderBy('e.id', 'ASC')->addOrderBy('l.id', 'ASC');
        if ($validatedOnly) {
            $qb->andWhere('e.validatedAt IS NOT NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
