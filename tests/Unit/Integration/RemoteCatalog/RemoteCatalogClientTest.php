<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Integration\RemoteCatalog;

use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogClient;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogConfigResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(RemoteCatalogClient::class)]
final class RemoteCatalogClientTest extends TestCase
{
    /** @var array{url: string, args: array<string, mixed>} */
    private array $lastRequest = ['url' => '', 'args' => []];

    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        when('get_option')->justReturn(false);
        when('is_wp_error')->justReturn(false);
        when('wp_remote_retrieve_headers')->justReturn([]);
        when('wp_remote_retrieve_response_code')->justReturn(200);
        when('wp_remote_retrieve_body')->justReturn('[]');
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }

    public function testFetchWidgetNamesMapsTheResponseAndSendsABearerToken(): void
    {
        $this->respondWith('[{"name":"Alpha"},{"name":"Beta"},{"nope":"skip"}]');

        $names = $this->client('https://api.test/', 'tok')->fetchWidgetNames();

        self::assertSame(['Alpha', 'Beta'], $names);
        self::assertSame('GET', $this->lastRequest['args']['method'] ?? null);
        self::assertSame('https://api.test/widgets', $this->lastRequest['url']);

        $headers = $this->lastRequest['args']['headers'] ?? [];
        self::assertSame('Bearer tok', $headers['Authorization'] ?? null);
    }

    public function testReturnsEmptyOnANonSuccessfulResponse(): void
    {
        when('wp_remote_retrieve_response_code')->justReturn(503);
        $this->respondWith('[{"name":"Alpha"}]');

        self::assertSame([], $this->client('https://api.test', 'tok')->fetchWidgetNames());
    }

    public function testNeverThrowsOnInvalidJson(): void
    {
        $this->respondWith('not-json');

        self::assertSame([], $this->client('https://api.test', 'tok')->fetchWidgetNames());
    }

    public function testDoesNotCallTheApiWhenUnconfigured(): void
    {
        $called = false;
        when('wp_remote_request')->alias(function () use (&$called): array {
            $called = true;

            return [];
        });

        $client = $this->client('', '');

        self::assertFalse($client->isConfigured());
        self::assertSame([], $client->fetchWidgetNames());
        self::assertFalse($called, 'no HTTP request is made without a base URL');
    }

    private function respondWith(string $json): void
    {
        when('wp_remote_retrieve_body')->justReturn($json);
        when('wp_remote_request')->alias(function (string $url, array $args): array {
            $this->lastRequest = ['url' => $url, 'args' => $args];

            return [];
        });
    }

    private function client(string $baseUrl, string $apiKey): RemoteCatalogClient
    {
        $config = new class($baseUrl, $apiKey) implements ConfigResolverInterface {
            public function __construct(
                private readonly string $baseUrl,
                private readonly string $apiKey,
            ) {}

            public function get(string $key, ?string $entitySlug = null, string $default = ''): string
            {
                return match ($key) {
                    'remotecatalog_baseurl' => $this->baseUrl,
                    'remotecatalog_apikey' => $this->apiKey,
                    default => $default,
                };
            }

            public function has(string $key, ?string $entitySlug = null): bool
            {
                return $this->get($key) !== '';
            }
        };

        return new RemoteCatalogClient(new RemoteCatalogConfigResolver($config));
    }
}
