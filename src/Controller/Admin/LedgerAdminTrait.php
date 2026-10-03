<?php

namespace Base\Ledger\Controller\Admin;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Ledger\Entity\Book;
use Base\Ledger\Security\Voter\LedgerVoter;

/**
 * The ledger screens' permissions (LedgerVoter), for every ledger CRUD:
 * reading takes LEDGER_VIEW, changing LEDGER_MANAGE - ledger.admin_role
 * (ROLE_ADMIN) by default, rather than the admin's blanket ROLE_SUPERADMIN.
 * Asked about a record, the voter looks at its book.
 */
trait LedgerAdminTrait
{
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setEntityPermission(LedgerVoter::VIEW);
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)->setPermissions(array_fill_keys([
            Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE,
            Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE, Action::SAVE_AND_ADD_ANOTHER,
        ], LedgerVoter::MANAGE));
    }

    /** The book a new record belongs to until the form says otherwise: the first one. */
    protected function defaultBook(): Book
    {
        return $this->entityManager->getRepository(Book::class)->findOneBy([], ['id' => 'ASC'])
            ?? throw $this->createNotFoundException('Create a book first (Ledger > Books).');
    }

    /** A token in an action's link, as for a logout: a link from elsewhere, a prefetch, does nothing. */
    protected function actionToken(string $action, ?int $id): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken($action.'_'.$id)->getValue();
    }

    protected static function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
