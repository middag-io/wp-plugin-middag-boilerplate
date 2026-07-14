<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\WordPress\Hook;

use Middag\WordPress\Hook\Contract\HookInterface;
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;

/**
 * Demonstrates the hook seam: a class (not scattered add_action calls) that the
 * {@see HookRegistrar} discovers by its 'Hooks' suffix and
 * resolves from the container — so a hook can take constructor dependencies.
 *
 * Here it exposes the active-widget count as a WordPress filter backed by the
 * injected domain service.
 */
final readonly class DemoHooks implements HookInterface
{
    public function __construct(private WidgetService $widgets) {}

    public function register(): void
    {
        add_filter('middag_boilerplate/active_widget_count', [$this, 'activeWidgetCount']);
    }

    public function activeWidgetCount(mixed $value = 0): int
    {
        return count($this->widgets->listActive());
    }
}
