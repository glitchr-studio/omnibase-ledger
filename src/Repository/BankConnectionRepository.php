<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\BankConnection;
use Base\Ledger\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankConnection> */
class BankConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankConnection::class);
    }

    /** @return list<BankConnection> */
    public function forBook(?Book $book = null): array
    {
        return null === $book ? $this->findBy([], ['id' => 'ASC']) : $this->findBy(['book' => $book], ['id' => 'ASC']);
    }
}
