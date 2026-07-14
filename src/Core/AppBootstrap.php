<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Core;

use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\Auth\WpSessionAuthenticator;
use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Runtime\WpBootstrap;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The plugin's composition root: it COMPOSES the WordPress adapter bootstrap
 * rather than reimplementing the platform seam (as an application plugin that
 * owns the full stack would).
 *
 * {@see WpBootstrap} binds the platform contracts (wpdb connection, translator,
 * config, user context, ...); this class only adds what is plugin-specific:
 * the auth seam, the Inertia adapter, and convention-based discovery of the
 * plugin's own services via {@see AppServiceProvider}.
 */
final readonly class AppBootstrap implements BootstrapInterface
{
    /**
     * @param string $projectRoot the directory that CONTAINS src/ (no trailing
     *                            slash) — ServiceProvider scans from here
     */
    public function __construct(private string $projectRoot) {}

    public function configure(ContainerBuilder $builder): void
    {
        // Compose the platform adapter — never re-wire the wpdb/translator/etc.
        // bindings it already provides.
        (new WpBootstrap())->configure($builder);

        // The component context is injected at runtime (see Plugin::boot); declare
        // its synthetic slot so services can autowire HostComponentContextInterface.
        $builder->register(HostComponentContextInterface::class)
            ->setSynthetic(true)
            ->setPublic(true)
        ;

        // Auth seam: cookie/session by default. A product that needs bearer/JWT
        // composes its own RequestAuthenticatorInterface and binds it here instead.
        $builder->register(RequestAuthenticatorInterface::class, WpSessionAuthenticator::class)
            ->setPublic(true)
        ;

        // Per-component Inertia adapter — autowires the component context above;
        // the response emitter falls back to its PhpSapiEmitter default.
        $builder->register(InertiaAdapter::class, InertiaAdapter::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // Discover the plugin's own Domain/WordPress/Api/UI services by convention.
        AppServiceProvider::register($builder, $this->projectRoot);
    }

    public function platform(): string
    {
        return 'wordpress';
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [];
    }
}
