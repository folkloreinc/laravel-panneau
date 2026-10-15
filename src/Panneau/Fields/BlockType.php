<?php

namespace Panneau\Fields;

/**
 * A type of block of a repeater, with its own fields.
 */
final class BlockType
{
    private ?string $icon = null;

    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        private string $id,
        private string $label,
        private array $fields = [],
    ) {}

    /**
     * @param  list<Field>  $fields
     */
    public static function make(string $id, string $label, array $fields = []): self
    {
        return new self($id, $label, $fields);
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * @param  list<Field>  $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'fields' => array_map(fn (Field $field): array => $field->toDefinition(), $this->fields),
        ], fn (mixed $value): bool => $value !== null);
    }
}
