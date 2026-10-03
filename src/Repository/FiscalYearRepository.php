<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\FiscalYear;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FiscalYear> */
class FiscalYearRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FiscalYear::class);
    }

    /** The fiscal year of $book that $date falls in, if the book has one. */
    public function containing(Book $book, \DateTimeInterface $date): ?FiscalYear
    {
        return $this->createQueryBuilder('y')
            ->andWhere('y.book = :book')->setParameter('book', $book)
            ->andWhere('y.start <= :date AND y.end >= :date')->setParameter('date', \DateTimeImmutable::createFromInterface($date)->setTime(0, 0), 'date_immutable')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** The fiscal year closing in $year, else the calendar year $year (unsaved). */
    public function forYear(Book $book, int $year): FiscalYear
    {
        $years = $this->createQueryBuilder('y')
            ->andWhere('y.book = :book')->setParameter('book', $book)
            ->orderBy('y.end', 'DESC')
            ->getQuery()->getResult();
        foreach ($years as $fiscalYear) {
            if ((int) $fiscalYear->getEnd()->format('Y') === $year) {
                return $fiscalYear;
            }
        }

        return FiscalYear::calendar($book, $year);
    }

    /** The fiscal year $date falls in, or its calendar year. */
    public function current(Book $book, ?\DateTimeImmutable $date = null): FiscalYear
    {
        $date ??= new \DateTimeImmutable('today');

        return $this->containing($book, $date) ?? FiscalYear::calendar($book, (int) $date->format('Y'));
    }
}
