<?php

// Standalone (composer install in this checkout), inside a host application
// (vendor/omnibase/ledger), or against a host's vendor dir mounted elsewhere
// (LEDGER_AUTOLOAD, /srv/app/vendor in the docker runner): whichever
// autoloader exists is used, and the namespaces under test are registered by
// hand because a host's autoloader never reads a dependency's autoload-dev.
$candidates = array_filter([
    getenv('LEDGER_AUTOLOAD') ?: null,
    __DIR__.'/../vendor/autoload.php',
    __DIR__.'/../../../autoload.php',
    '/srv/app/vendor/autoload.php',
]);

foreach ($candidates as $candidate) {
    if (!is_file($candidate)) {
        continue;
    }

    $loader = require $candidate;
    $loader->addPsr4('Tests\\Base\\Ledger\\', __DIR__);
    // Prepended: the classes under test are this checkout's, even when a
    // host application has an installed copy of the bundle too.
    $loader->addPsr4('Base\\Ledger\\', __DIR__.'/../src', true);

    // glitchr/omnibank, when the host does not have it yet: a checkout next
    // to this one (~/Sites/omnibank) or mounted at /srv/omnibank.
    // Its "files" gateway too (omnibank/files), for the statement upload tests.
    // Asked of the loader's prefixes, not class_exists(): a class looked
    // for in vain is remembered missing, even after its prefix is added.
    foreach (['core' => 'Omnibank\\', 'files' => 'Omnibank\\Files\\'] as $package => $namespace) {
        if (isset($loader->getPrefixesPsr4()[$namespace])) {
            continue;
        }
        foreach ([getenv('OMNIBANK_PATH') ?: null, __DIR__.'/../../../omnibank', '/srv/omnibank'] as $omnibank) {
            if (null !== $omnibank && is_dir($omnibank.'/'.$package)) {
                $loader->addPsr4($namespace, $omnibank.'/'.$package);
                break;
            }
        }
    }

    return;
}

throw new RuntimeException('No autoloader found: run composer install in this checkout or install the bundle in an application.');
