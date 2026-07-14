<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withRootFiles()
    ->withBootstrapFiles([
        __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
    ])
    ->withImportNames(importShortClasses: false)
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        PHPUnitSetList::PHPUNIT_120,
    ])
    ->withSkip([
        // First-class callables ($obj->m(...)) create a fresh Closure each call,
        // so remove_action()/remove_filter() by reference stops working. Keep
        // the removable [$obj, 'method'] array form for WordPress hooks.
        ArrayToFirstClassCallableRector::class,
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
    ]);
