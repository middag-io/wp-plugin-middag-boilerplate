<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Integration\RemoteCatalog;

use Middag\Framework\Kernel\Contract\ConfigResolverInterface;

/**
 * Resolves the integration's configuration with env-first precedence.
 *
 * Discovered by convention (the 'Resolver' suffix) and autowired with the
 * framework's {@see ConfigResolverInterface}, whose resolution order is
 * environment variable → host config store → default. So `baseUrl()` reads
 * `REMOTECATALOG_BASEURL` first and only then falls back.
 *
 * The API key additionally falls back to a plaintext WordPress option
 * (`mdgbp_api_key`, set on the native Settings page) — the migration shape a
 * real product uses to move a secret from the options table to an env var
 * WITHOUT breaking installs that still store it the old way.
 */
final readonly class RemoteCatalogConfigResolver
{
    private const CONFIG_BASE_URL = 'remotecatalog_baseurl';

    private const CONFIG_API_KEY = 'remotecatalog_apikey';

    private const LEGACY_API_KEY_OPTION = 'mdgbp_api_key';

    public function __construct(private ConfigResolverInterface $config) {}

    public function baseUrl(): string
    {
        return $this->config->get(self::CONFIG_BASE_URL);
    }

    public function apiKey(): string
    {
        $blessed = $this->config->get(self::CONFIG_API_KEY);

        return $blessed !== '' ? $blessed : $this->legacyApiKey();
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '';
    }

    private function legacyApiKey(): string
    {
        $value = get_option(self::LEGACY_API_KEY_OPTION);

        return is_string($value) ? trim($value) : '';
    }
}
