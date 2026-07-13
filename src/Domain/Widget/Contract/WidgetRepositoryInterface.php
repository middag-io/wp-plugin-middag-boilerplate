<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Domain\Widget\Contract;

use Middag\WpBoilerplate\Domain\Widget\Widget;

/**
 * Persistence contract for widgets, owned by the Domain.
 *
 * The WordPress layer's concrete repository implements it (and is auto-aliased
 * to it by the framework ServiceProvider — the single project-scoped interface
 * a class implements becomes its service id), so the domain service depends on
 * this abstraction, never on wpdb.
 */
interface WidgetRepositoryInterface
{
    public function store(Widget $widget): void;

    public function ofId(int $id): ?Widget;

    /**
     * @return list<Widget>
     */
    public function allActive(): array;
}
