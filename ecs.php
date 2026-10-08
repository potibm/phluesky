<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRules([
        NoUnusedImportsFixer::class,
    ])
    ->withPreparedSets(
        spaces: true,
        arrays: true,
        docblocks: true,
        namespaces: true,
        comments: true,
        psr12: true,
    );
