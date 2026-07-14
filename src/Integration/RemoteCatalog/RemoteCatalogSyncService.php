<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Integration\RemoteCatalog;

use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\WidgetData;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;

/**
 * Orchestrates the integration against the domain — the shape a real product's
 * "import from an external system" job takes.
 *
 * Discovered by convention (the 'Service' suffix). It depends on the
 * {@see RemoteCatalogClientInterface} seam (resolved through the hand-wired
 * alias) and reuses the existing {@see WidgetService} to persist — an
 * integration adds a data SOURCE, it does not re-implement the domain.
 */
final readonly class RemoteCatalogSyncService
{
    public function __construct(
        private RemoteCatalogClientInterface $catalog,
        private WidgetService $widgets,
    ) {}

    /**
     * Import the remote catalog's widgets into the local domain.
     *
     * @return int the number of widgets imported (0 when the integration is
     *             unconfigured or the remote returned nothing)
     */
    public function import(): int
    {
        if (!$this->catalog->isConfigured()) {
            return 0;
        }

        $imported = 0;
        foreach ($this->catalog->fetchWidgetNames() as $name) {
            $this->widgets->create(new WidgetData($name, WidgetStatus::Active));
            ++$imported;
        }

        return $imported;
    }
}
