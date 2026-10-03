<?php

namespace Base\Ledger\Security\Voter;

use Base\Ledger\Entity\Book;
use Base\Ledger\Security\BookAccessInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * LEDGER_VIEW and LEDGER_MANAGE, on a Book or on anything of one (an entry,
 * an account, a bank line: whatever has getBook()):
 *
 * - ledger.admin_role (ROLE_ADMIN by default) views and manages every book;
 * - the application's BookAccessInterface services may grant a user a book
 *   (managing implies viewing).
 *
 * Without a subject - the back office's ledger screens as a whole - only
 * the admin role is granted.
 */
final class LedgerVoter extends Voter
{
    public const VIEW = 'LEDGER_VIEW';
    public const MANAGE = 'LEDGER_MANAGE';

    /** @param iterable<BookAccessInterface> $access */
    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        #[AutowireIterator('ledger.book_access')] private readonly iterable $access = [],
        #[Autowire('%ledger.admin_role%')] private readonly string $adminRole = 'ROLE_ADMIN',
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute || self::MANAGE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->accessDecisionManager->decide($token, [$this->adminRole])) {
            return true;
        }

        $user = $token->getUser();
        $book = null === $subject ? null : self::bookOf($subject);
        if (!$user instanceof UserInterface || null === $book) {
            return false;
        }

        foreach ($this->access as $access) {
            if ($access->canManage($user, $book) || (self::VIEW === $attribute && $access->canView($user, $book))) {
                return true;
            }
        }

        return false;
    }

    public static function bookOf(mixed $subject): ?Book
    {
        if ($subject instanceof Book) {
            return $subject;
        }
        if (\is_object($subject) && method_exists($subject, 'getBook')) {
            $book = $subject->getBook();

            return $book instanceof Book ? $book : null;
        }

        return null;
    }
}
