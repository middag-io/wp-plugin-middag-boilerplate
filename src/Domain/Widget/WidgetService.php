<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Domain\Widget;

use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * Application service for widgets — pure orchestration over the repository
 * abstraction and the PSR-20 clock the framework binds by default.
 *
 * Constructor injection, no static calls, no WordPress. Discovered and wired by
 * the framework ServiceProvider ('Service' suffix); its {@see WidgetRepositoryInterface}
 * argument resolves to the WordPress-layer repository via the auto-alias.
 */
final readonly class WidgetService
{
    public function __construct(
        private WidgetRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {}

    /**
     * @return list<Widget>
     */
    public function listActive(): array
    {
        return $this->repository->allActive();
    }

    public function create(WidgetData $data): Widget
    {
        $widget = new Widget(
            null,
            $data->name,
            $data->status,
            $this->clock->now()->format('Y-m-d H:i:s'),
        );

        $this->repository->store($widget);

        return $widget;
    }
}
