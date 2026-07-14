<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Domain\Widget;

use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\WidgetData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetData::class)]
final class WidgetDataTest extends TestCase
{
    public function testDefaultsStatusToDraftWhenOmitted(): void
    {
        $data = new WidgetData('Sensor');

        self::assertSame('Sensor', $data->name);
        self::assertSame(WidgetStatus::Draft, $data->status);
    }

    public function testKeepsTheExplicitStatusItWasGiven(): void
    {
        $data = new WidgetData('Sensor', WidgetStatus::Active);

        self::assertSame('Sensor', $data->name);
        self::assertSame(WidgetStatus::Active, $data->status);
    }
}
