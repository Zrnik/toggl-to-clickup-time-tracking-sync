<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

/** @noinspection PhpUnhandledExceptionInspection */
return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bin',
        __DIR__ . '/ecs.php',
    ])
    ->withRules([
        NoUnusedImportsFixer::class,
        DeclareStrictTypesFixer::class,
    ])
    ->withPreparedSets(
        psr12: true,
        arrays: true,
        spaces: true,
        namespaces: true,
    );
