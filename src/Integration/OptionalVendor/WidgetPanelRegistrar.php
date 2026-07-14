<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Integration\OptionalVendor;

use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\WordPress\Hook\OptionalVendorHooks;
use OptionalVendor\Widgets\PanelRegistry;

/**
 * The single point of vendor coupling — it does the `new WidgetPanelExtension`
 * so the analysed {@see OptionalVendorHooks}
 * never has to reference a class whose parent is absent.
 *
 * Despite the 'Registrar' discovery suffix it is NOT auto-registered: it sits
 * under the ignored `OptionalVendor/` dir. It is `new`-ed by hand by the hook,
 * inside the vendor guard, so touching the vendor symbols here is safe.
 */
final readonly class WidgetPanelRegistrar
{
    public function __construct(private WidgetService $widgets) {}

    public function register(): void
    {
        PanelRegistry::register(new WidgetPanelExtension($this->widgets));
    }
}
