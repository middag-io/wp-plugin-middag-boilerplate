<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\WordPress\Repository;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\Framework\Persistence\Repository\AbstractRepository;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\WordPress\Persistence\WidgetMapper;

/**
 * Widget persistence over the framework's data-mapper repository.
 *
 * Inherits find/save/delete from {@see AbstractRepository} and adds the
 * domain-shaped finders {@see WidgetRepositoryInterface} declares. The framework
 * ServiceProvider auto-aliases this to that interface (its single project-scoped
 * interface), so {@see \Middag\WpBoilerplate\Domain\Widget\WidgetService}
 * receives it without any manual container wiring.
 *
 * The logical table name is resolved to its physical, `$wpdb`-prefixed form via
 * the connection dialect — the shape the wpdb adapter's record helpers expect.
 *
 * @extends AbstractRepository<Widget>
 */
final class WidgetRepository extends AbstractRepository implements WidgetRepositoryInterface
{
    private const LOGICAL_TABLE = 'mdgbp_widget';

    public function __construct(
        ConnectionAdapterInterface $connection,
        private readonly WidgetMapper $widgetMapper,
    ) {
        parent::__construct($connection);
    }

    public function store(Widget $widget): void
    {
        $this->save($widget);
    }

    public function ofId(int $id): ?Widget
    {
        $widget = $this->find($id);

        return $widget instanceof Widget ? $widget : null;
    }

    /**
     * @return list<Widget>
     */
    public function allActive(): array
    {
        $rows = $this->query()
            ->where('status', WidgetStatus::Active->value)
            ->orderByDesc('id')
            ->get();

        return array_map(
            static fn (array $row): Widget => Widget::fromArray($row),
            $rows,
        );
    }

    protected function table(): string
    {
        return $this->connection->dialect()->table(self::LOGICAL_TABLE);
    }

    /**
     * @return MapperInterface<Widget>
     */
    protected function mapper(): MapperInterface
    {
        return $this->widgetMapper;
    }
}
