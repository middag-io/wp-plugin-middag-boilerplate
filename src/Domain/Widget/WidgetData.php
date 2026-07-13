<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Domain\Widget;

use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;

/**
 * Immutable input for creating a widget — the sanitized payload the WordPress
 * layer hands the domain service, decoupled from the raw request.
 */
final readonly class WidgetData
{
    public function __construct(
        public string $name,
        public WidgetStatus $status = WidgetStatus::Draft,
    ) {}
}
