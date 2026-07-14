<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Domain\Widget\Enum;

use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetStatus::class)]
final class WidgetStatusTest extends TestCase
{
    public function testCasesExposeTheirBackingValues(): void
    {
        self::assertSame('draft', WidgetStatus::Draft->value);
        self::assertSame('active', WidgetStatus::Active->value);
        self::assertSame('archived', WidgetStatus::Archived->value);
    }

    public function testFromResolvesEachBackingValueToItsCase(): void
    {
        self::assertSame(WidgetStatus::Draft, WidgetStatus::from('draft'));
        self::assertSame(WidgetStatus::Active, WidgetStatus::from('active'));
        self::assertSame(WidgetStatus::Archived, WidgetStatus::from('archived'));
    }

    #[DataProvider('labelKeyProvider')]
    public function testLabelKeyPrefixesTheBackingValue(WidgetStatus $status, string $expected): void
    {
        self::assertSame($expected, $status->labelKey());
    }

    /**
     * @return iterable<string, array{WidgetStatus, string}>
     */
    public static function labelKeyProvider(): iterable
    {
        yield 'draft' => [WidgetStatus::Draft, 'widget.status.draft'];

        yield 'active' => [WidgetStatus::Active, 'widget.status.active'];

        yield 'archived' => [WidgetStatus::Archived, 'widget.status.archived'];
    }

    public function testIsActiveIsTrueOnlyForTheActiveCase(): void
    {
        self::assertTrue(WidgetStatus::Active->isActive());
        self::assertFalse(WidgetStatus::Draft->isActive());
        self::assertFalse(WidgetStatus::Archived->isActive());
    }
}
