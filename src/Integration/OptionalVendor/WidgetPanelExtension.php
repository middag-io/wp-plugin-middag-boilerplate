<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Integration\OptionalVendor;

use Middag\WpBoilerplate\Core\AppServiceProvider;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\WordPress\Hook\OptionalVendorHooks;
use OptionalVendor\Widgets\PanelBase;

/**
 * PEGADINHA #1 — a class that `extends` a base provided ONLY by an optional
 * third-party plugin (here the fictional `OptionalVendor\Widgets\PanelBase`;
 * in a real product it is `WC_Email`, `ElementorPro\...\Action_Base`, ...).
 *
 * On a site WITHOUT that plugin the base class does not exist, so:
 *  1. convention discovery must NOT scan this file — reflecting the class would
 *     autoload it and FATAL on the missing parent at container-build time. The
 *     whole `OptionalVendor/` dir is therefore listed in
 *     {@see AppServiceProvider}'s IGNORE_DIRS.
 *  2. PHPStan cannot resolve the parent (no stub), so the dir is listed in
 *     `.phpstan.neon` excludePaths.analyse.
 *  3. it is instantiated ONLY behind a runtime guard that proves the plugin is
 *     active (see {@see OptionalVendorHooks}),
 *     reached through {@see WidgetPanelRegistrar} so no ANALYSED file ever
 *     references the absent vendor symbol.
 *
 * A hand-instantiated glue class can still take container-resolved dependencies:
 * the registrar passes the {@see WidgetService} it resolved from the container.
 */
final class WidgetPanelExtension extends PanelBase
{
    public function __construct(private readonly WidgetService $widgets)
    {
        parent::__construct('mdgbp_widgets');
    }

    public function content(): string
    {
        return sprintf('Active widgets: %d', count($this->widgets->listActive()));
    }
}
