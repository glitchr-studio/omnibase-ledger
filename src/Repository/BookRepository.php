<?php

namespace Base\Ledger\Repository;

use Base\Ledger\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Book> */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /** A book by its id, SIREN or exact name: what the commands accept. */
    public function resolve(string $handle): ?Book
    {
        if (ctype_digit($handle) && null !== ($book = $this->find((int) $handle))) {
            return $book;
        }

        $siren = preg_replace('/\D/', '', $handle);
        if (9 === \strlen($siren) && null !== ($book = $this->findOneBy(['siren' => $siren]))) {
            return $book;
        }

        return $this->findOneBy(['name' => $handle]);
    }
}
