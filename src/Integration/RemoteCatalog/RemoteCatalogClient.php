<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Integration\RemoteCatalog;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\WordPress\Http\Client\HttpClient;
use Middag\WpBoilerplate\Core\AppServiceProvider;

/**
 * The concrete transport — talks to the remote catalog over the WordPress HTTP
 * API via the wp-adapter's {@see HttpClient} (NO third-party SDK: the client
 * respects the site's HTTP stack, proxy and filters).
 *
 * Load-bearing wiring note: the `Client` short name is NOT a discovery suffix,
 * so this class is bound BY HAND in {@see AppServiceProvider}
 * and its interface aliased there. Its config collaborator (the discovered
 * {@see RemoteCatalogConfigResolver}) is autowired, so the rest of the graph
 * resolves by convention.
 *
 * Never throws: transport/JSON failures are caught and degrade to an empty
 * result, honouring the {@see RemoteCatalogClientInterface} contract.
 */
final readonly class RemoteCatalogClient implements RemoteCatalogClientInterface
{
    private HttpClient $http;

    public function __construct(private RemoteCatalogConfigResolver $config)
    {
        $this->http = new HttpClient(['timeout' => 20]);
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function fetchWidgetNames(): array
    {
        if (!$this->config->isConfigured()) {
            return [];
        }

        $args = [];
        $apiKey = $this->config->apiKey();
        if ($apiKey !== '') {
            $args['headers'] = ['Authorization' => 'Bearer ' . $apiKey];
        }

        try {
            $response = $this->http->request('GET', rtrim($this->config->baseUrl(), '/') . '/widgets', $args);
            if (!$response->ok()) {
                return [];
            }

            $names = [];
            foreach ($response->json() as $row) {
                if (is_array($row) && isset($row['name']) && is_string($row['name'])) {
                    $names[] = $row['name'];
                }
            }

            return $names;
        } catch (MiddagInfrastructureException) {
            return [];
        }
    }
}
