<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Integration\RemoteCatalog;

use DateTimeImmutable;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogClientInterface;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogSyncService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(RemoteCatalogSyncService::class)]
final class RemoteCatalogSyncServiceTest extends TestCase
{
    public function testImportCreatesAnActiveWidgetPerRemoteName(): void
    {
        $repository = $this->inMemoryRepository();
        $widgets = new WidgetService($repository, $this->fixedClock('2026-07-14 12:00:00'));
        $service = new RemoteCatalogSyncService($this->catalog(true, ['Alpha', 'Beta']), $widgets);

        self::assertSame(2, $service->import());
        self::assertCount(2, $repository->stored);
        self::assertSame('Alpha', $repository->stored[0]->name());
        self::assertSame(WidgetStatus::Active, $repository->stored[0]->status());
    }

    public function testImportIsANoOpWhenTheIntegrationIsUnconfigured(): void
    {
        $repository = $this->inMemoryRepository();
        $widgets = new WidgetService($repository, $this->fixedClock('2026-07-14 12:00:00'));
        $service = new RemoteCatalogSyncService($this->catalog(false, ['Alpha']), $widgets);

        self::assertSame(0, $service->import());
        self::assertCount(0, $repository->stored);
    }

    /**
     * @param list<string> $names
     */
    private function catalog(bool $configured, array $names): RemoteCatalogClientInterface
    {
        return new class($configured, $names) implements RemoteCatalogClientInterface {
            /**
             * @param list<string> $names
             */
            public function __construct(
                private readonly bool $configured,
                private readonly array $names,
            ) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function fetchWidgetNames(): array
            {
                return $this->names;
            }
        };
    }

    private function fixedClock(string $moment): ClockInterface
    {
        return new class($moment) implements ClockInterface {
            public function __construct(private readonly string $moment) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->moment);
            }
        };
    }

    private function inMemoryRepository(): WidgetRepositoryInterface
    {
        return new class implements WidgetRepositoryInterface {
            /** @var list<Widget> */
            public array $stored = [];

            public function store(Widget $widget): void
            {
                $this->stored[] = $widget;
            }

            public function ofId(int $id): ?Widget
            {
                return null;
            }

            public function allActive(): array
            {
                return $this->stored;
            }
        };
    }
}
