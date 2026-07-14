<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
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
        // The optional-vendor guard checks class_exists() on a STRING vendor name
        // on purpose: the class exists only when the third-party plugin is active,
        // so it must never become a `Vendor\Base::class` reference (that couples
        // the code to a symbol a test fixture happens to define). WidgetPanelTest
        // defines the fake vendor base at file scope so it can test the glue that
        // extends it, which makes rector able to resolve the name — skip the rule
        // for the two files that intentionally keep the string form.
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/WordPress/Hook/OptionalVendorHooks.php',
            __DIR__ . '/tests/Unit/WordPress/Hook/OptionalVendorHooksTest.php',
        ],
    ]);
