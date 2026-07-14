<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\WordPress\Persistence;

use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\Framework\Persistence\Contract\MapperInterface;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\WordPress\Repository\WidgetRepository;

/**
 * Hydrates/dehydrates {@see Widget} across the persistence seam so the entity
 * stays ignorant of storage. Auto-discovered ('Mapper' suffix) and injected
 * into {@see WidgetRepository}.
 *
 * @implements MapperInterface<Widget>
 */
final class WidgetMapper implements MapperInterface
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $metadata
     */
    public function dbToDomain(array $record, array $metadata): EntityInterface
    {
        return Widget::fromArray($record);
    }

    /**
     * @return array<string, mixed>
     */
    public function domainToDb(EntityInterface $entity): array
    {
        $record = $entity->toArray();

        // Let the auto-increment column assign the id on insert.
        if (($record['id'] ?? null) === null) {
            unset($record['id']);
        }

        return $record;
    }
}
