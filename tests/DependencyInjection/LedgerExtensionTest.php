<?php

namespace Tests\Base\Ledger\DependencyInjection;

use Base\Ledger\Bank\Connections;
use Base\Ledger\DependencyInjection\LedgerExtension;
use Base\Ledger\Reconciliation\Reconciler;
use Base\Ledger\Service\Posting;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LedgerExtensionTest extends TestCase
{
    public function testDefaultsAndServices(): void
    {
        $container = new ContainerBuilder();
        (new LedgerExtension())->load([[]], $container);

        self::assertFalse($container->getParameter('ledger.auto_accounts'));
        self::assertSame(0.9, $container->getParameter('ledger.reconciliation.auto_threshold'));
        self::assertSame(12, $container->getParameter('ledger.reconciliation.max_group_items'));
        self::assertSame(5, $container->getParameter('ledger.bank.sync_overlap_days'));
        self::assertSame('ROLE_ADMIN', $container->getParameter('ledger.admin_role'));
        self::assertSame('sci', $container->getParameter('ledger.chart'));
        self::assertSame('%kernel.secret%', $container->getParameter('ledger.secret'));

        foreach ([Posting::class, Reconciler::class, Connections::class, 'Base\\Ledger\\Controller\\WebhookController', 'Base\\Ledger\\Admin\\Widget\\BankWidgetType', 'Base\\Ledger\\Controller\\Admin\\Crud\\EntryCrudController'] as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }
        foreach (['Base\\Ledger\\Entity\\Entry', 'Base\\Ledger\\Model\\EntryDraft', 'Base\\Ledger\\Reconciliation\\Suggestion', 'Base\\Ledger\\Exception\\PostingException'] as $id) {
            // Not a service: not registered, or registered excluded (Symfony keeps excluded files as abstract hints).
            self::assertTrue(!$container->hasDefinition($id) || $container->getDefinition($id)->hasTag('container.excluded'), $id);
        }
    }

    public function testConfiguredValues(): void
    {
        $container = new ContainerBuilder();
        (new LedgerExtension())->load([['auto_accounts' => true, 'reconciliation' => ['auto_threshold' => 0.95]]], $container);

        self::assertTrue($container->getParameter('ledger.auto_accounts'));
        self::assertSame(0.95, $container->getParameter('ledger.reconciliation.auto_threshold'));
    }
}
