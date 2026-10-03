<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\Journal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Journal> */
class JournalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Journal::class);
    }

    public function findOneByCode(Book $book, string $code): ?Journal
    {
        return $this->findOneBy(['book' => $book, 'code' => strtoupper($code)]);
    }
}
