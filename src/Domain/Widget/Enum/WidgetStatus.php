<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Domain\Widget\Enum;

/**
 * Lifecycle state of a {@see \Middag\WpBoilerplate\Domain\Widget\Widget}.
 *
 * Lives under Enum/ so the framework ServiceProvider skips it during discovery
 * (it is a value, not a service).
 */
enum WidgetStatus: string
{
    case Draft = 'draft';

    case Active = 'active';

    case Archived = 'archived';

    /**
     * Translation key for the status label. Kept as a plain key so the Domain
     * stays WordPress-free — the WordPress/UI layer resolves it to a string.
     */
    public function labelKey(): string
    {
        return 'widget.status.' . $this->value;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
