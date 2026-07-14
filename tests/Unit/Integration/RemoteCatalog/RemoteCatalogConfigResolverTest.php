<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Integration\RemoteCatalog;

use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogConfigResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(RemoteCatalogConfigResolver::class)]
final class RemoteCatalogConfigResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }

    public function testBaseUrlAndIsConfiguredComeFromConfig(): void
    {
        $resolver = new RemoteCatalogConfigResolver($this->config(['remotecatalog_baseurl' => 'https://api.test']));

        self::assertSame('https://api.test', $resolver->baseUrl());
        self::assertTrue($resolver->isConfigured());
    }

    public function testIsNotConfiguredWithoutABaseUrl(): void
    {
        $resolver = new RemoteCatalogConfigResolver($this->config([]));

        self::assertSame('', $resolver->baseUrl());
        self::assertFalse($resolver->isConfigured());
    }

    public function testApiKeyPrefersConfigOverTheLegacyOption(): void
    {
        when('get_option')->justReturn('legacy-key');

        $resolver = new RemoteCatalogConfigResolver($this->config(['remotecatalog_apikey' => 'blessed-key']));

        self::assertSame('blessed-key', $resolver->apiKey());
    }

    public function testApiKeyFallsBackToTheTrimmedLegacyPlaintextOption(): void
    {
        when('get_option')->justReturn('  legacy-key  ');

        $resolver = new RemoteCatalogConfigResolver($this->config([]));

        self::assertSame('legacy-key', $resolver->apiKey());
    }

    /**
     * @param array<string, string> $values
     */
    private function config(array $values): ConfigResolverInterface
    {
        return new class($values) implements ConfigResolverInterface {
            /**
             * @param array<string, string> $values
             */
            public function __construct(private readonly array $values) {}

            public function get(string $key, ?string $entitySlug = null, string $default = ''): string
            {
                return $this->values[$key] ?? $default;
            }

            public function has(string $key, ?string $entitySlug = null): bool
            {
                return ($this->values[$key] ?? '') !== '';
            }
        };
    }
}
