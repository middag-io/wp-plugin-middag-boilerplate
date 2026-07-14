<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Domain\Widget;

use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\SyncWidgetsService;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncWidgetsService::class)]
final class SyncWidgetsServiceTest extends TestCase
{
    public function testRunReturnsTheActiveWidgetCount(): void
    {
        $repository = new class implements WidgetRepositoryInterface {
            public function store(Widget $widget): void {}

            public function ofId(int $id): ?Widget
            {
                return null;
            }

            public function allActive(): array
            {
                return [
                    new Widget(1, 'A', WidgetStatus::Active, '2026-01-01 00:00:00'),
                    new Widget(2, 'B', WidgetStatus::Active, '2026-01-01 00:00:00'),
                ];
            }
        };

        self::assertSame(2, (new SyncWidgetsService($repository))->run());
    }
}
