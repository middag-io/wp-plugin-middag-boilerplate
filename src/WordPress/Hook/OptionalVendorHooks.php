<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\WordPress\Hook;

use Middag\WordPress\Hook\Contract\HookInterface;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\Integration\OptionalVendor\WidgetPanelExtension;
use Middag\WpBoilerplate\Integration\OptionalVendor\WidgetPanelRegistrar;

/**
 * Discovered hook that wires the optional third-party integration — the clean,
 * always-analysed side of the PEGADINHA #1 pattern
 * (see {@see WidgetPanelExtension}).
 *
 * It references NO vendor symbol directly: the guard uses a STRING class name
 * (never `Vendor\Base::class`, which PHPStan would try to resolve), and the
 * hand-instantiation is delegated to {@see WidgetPanelRegistrar} — which lives
 * in the excluded subtree, so this file needs no stub and no PHPStan exclusion.
 */
final readonly class OptionalVendorHooks implements HookInterface
{
    public function __construct(private WidgetService $widgets) {}

    public function register(): void
    {
        // The vendor base only exists when the optional plugin is active; on a
        // plain site this short-circuits and the glue is never loaded.
        if (!class_exists('OptionalVendor\Widgets\PanelBase')) {
            return;
        }

        (new WidgetPanelRegistrar($this->widgets))->register();
    }
}
