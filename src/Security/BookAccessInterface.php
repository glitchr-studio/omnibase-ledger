<?php

namespace Base\Ledger\Security;

use Base\Ledger\Entity\Book;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who else may reach a book than ledger.admin_role: an application's
 * service implementing this (autoconfigured, tag ledger.book_access) is
 * asked by LedgerVoter - an associate viewing their company's book, an
 * accountant managing their clients'. Any service saying yes is enough.
 */
#[AutoconfigureTag('ledger.book_access')]
interface BookAccessInterface
{
    public function canView(UserInterface $user, Book $book): bool;

    public function canManage(UserInterface $user, Book $book): bool;
}
