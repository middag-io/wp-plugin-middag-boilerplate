<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Integration\RemoteCatalog;

/**
 * The seam for an outbound third-party integration.
 *
 * Layer contract: an `Integration/<Vendor>/` package talks to ONE external
 * service. Its collaborators depend on THIS interface, never the concrete
 * {@see RemoteCatalogClient} — so the whole graph is testable with an in-memory
 * fake and the transport can be swapped without touching the domain.
 *
 * Implementations NEVER throw: a remote failure degrades to a null/empty result
 * (see {@see RemoteCatalogClient}), because an integration being down must not
 * take a request down with it.
 */
interface RemoteCatalogClientInterface
{
    /**
     * True when the integration has enough configuration to attempt a call.
     */
    public function isConfigured(): bool;

    /**
     * Fetch the widget names offered by the remote catalog.
     *
     * @return list<string> empty when unconfigured or on any remote failure
     */
    public function fetchWidgetNames(): array;
}
