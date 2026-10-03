<?php

namespace Base\Ledger\DependencyInjection;

use Base\Bundle\AbstractBaseExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class LedgerExtension extends AbstractBaseExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container): LedgerConfiguration
    {
        return new LedgerConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');

        $configuration = new LedgerConfiguration();
        $config = (new Processor())->processConfiguration($configuration, $configs);

        // ledger.auto_accounts, ledger.reconciliation.auto_threshold...
        $this->setConfiguration($container, $config, $configuration->getConfigTreeBuilder()->buildTree()->getName());
    }
}
