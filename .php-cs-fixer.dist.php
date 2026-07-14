<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// The main plugin file's header is a WordPress-parsed docblock (Plugin Name,
// Domain Path, ...) — cs-fixer's phpdoc rules would mangle it (e.g. append a
// period to "Domain Path: /languages"), so it is intentionally not fixed here.
$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'concat_space' => ['spacing' => 'one'],
        // Import classes (the code already does), but leave WordPress global
        // functions/constants unqualified — the WordPress idiom.
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'native_function_invocation' => false,
        'yoda_style' => false,
        'no_superfluous_phpdoc_tags' => false,
        // Tests carry #[CoversClass]; don't demand @covers docblocks or @internal.
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
    ])
    ->setFinder($finder);
