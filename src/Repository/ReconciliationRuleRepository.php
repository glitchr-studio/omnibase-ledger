<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\Book;
use Base\Ledger\Entity\ReconciliationRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReconciliationRule> */
class ReconciliationRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReconciliationRule::class);
    }

    /** @return list<ReconciliationRule> a book's enabled rules, highest priority first */
    public function forBook(Book $book): array
    {
        return $this->findBy(['book' => $book, 'enabled' => true], ['priority' => 'DESC', 'id' => 'ASC']);
    }
}
