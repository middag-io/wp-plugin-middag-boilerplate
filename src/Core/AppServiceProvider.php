<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Core;

use Middag\Framework\Kernel\ServiceProvider;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;

/**
 * Convention-based service discovery for the plugin.
 *
 * Extends the framework {@see ServiceProvider} with this plugin's namespace and
 * the layer directories to scan. Discovery registers every class whose short
 * name ends in a framework REGISTER_SUFFIX (Service, Repository, Controller,
 * Hooks, ...) and auto-aliases single-interface implementations — so a
 * {@see WidgetService} and its interface
 * are wired with zero manual container calls.
 *
 * Load-bearing contract (see ServiceProvider::register): SCAN_DIRS resolve
 * relative to the project root and MUST include the `src/` segment, and
 * ROOT_NAMESPACE MUST map to the PSR-4 root of that src/ tree. Core/ is
 * intentionally excluded — the composition root is wired by hand, not
 * discovered.
 */
final class AppServiceProvider extends ServiceProvider
{
    protected const ROOT_NAMESPACE = 'Middag\WpBoilerplate';

    protected const SCAN_DIRS = [
        'src/Domain',
        'src/WordPress',
        'src/Api',
        'src/UI',
    ];
}
