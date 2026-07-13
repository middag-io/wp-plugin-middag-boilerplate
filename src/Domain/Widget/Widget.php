<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Domain\Widget;

use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;

/**
 * A widget — the boilerplate's single demo aggregate.
 *
 * Pure PHP with ZERO WordPress coupling: it knows nothing about wpdb, options,
 * post types or the request. Persistence is a separate concern handled by the
 * WordPress layer's repository + mapper; the schema shape lives in
 * {@see WidgetSchema}. Behaviour (status transitions) belongs here, on the
 * entity, not in a service.
 */
final class Widget implements EntityInterface
{
    public function __construct(
        private ?int $id,
        private string $name,
        private WidgetStatus $status,
        private string $createdAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Assign the database id after an insert (the repository owns this call).
     */
    public function assignId(int $id): void
    {
        $this->id = $id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): WidgetStatus
    {
        return $this->status;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function activate(): void
    {
        $this->status = WidgetStatus::Active;
    }

    public function archive(): void
    {
        $this->status = WidgetStatus::Archived;
    }

    /**
     * @return array{id: ?int, name: string, status: string, created_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @return array{id: ?int, name: string, status: string, created_at: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Rebuild an entity from a persistence row.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $id = $row['id'] ?? null;

        return new self(
            $id === null ? null : (int) $id,
            (string) ($row['name'] ?? ''),
            WidgetStatus::from((string) ($row['status'] ?? WidgetStatus::Draft->value)),
            (string) ($row['created_at'] ?? ''),
        );
    }
}
