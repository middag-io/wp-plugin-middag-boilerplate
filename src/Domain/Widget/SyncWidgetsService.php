<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Domain\Widget;

use Middag\WordPress\Cron\CronHandler;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;

/**
 * The task a scheduled WP-Cron event runs. A real plugin would reconcile with an
 * external source here; the boilerplate just counts active widgets to prove the
 * cron → container → domain path end to end.
 *
 * Resolved from the container by {@see CronHandler} when
 * the event fires, so it gets its repository via normal constructor injection.
 */
final readonly class SyncWidgetsService
{
    public function __construct(private WidgetRepositoryInterface $repository) {}

    public function run(): int
    {
        return count($this->repository->allActive());
    }
}
