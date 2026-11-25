<?php

namespace Panneau\Fields;

use InvalidArgumentException;
use Panneau\Resources\RequestCache;

/**
 * A type of block of a repeater, with its own fields.
 */
final class BlockType
{
    private ?string $icon = null;

    private ?string $description = null;

    private ?string $itemTitle = null;

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
        RequestCache::forgetInRequest($this);

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
        RequestCache::forgetInRequest($this);

        return $this;
    }

    /**
     * What the block is for, under its label in the menu of types (panneau-js decision 0109).
     */
    public function description(string $description): self
    {
        $this->description = $description;
        RequestCache::forgetInRequest($this);

        return $this;
    }

    /**
     * The title of a block of this type in its collapsed header, in place of the repeater's
     * `settings.itemTitle` (panneau-js decision 0109): a field name (`heading`), or a template of
     * field names in braces (`{name} ({role})`).
     */
    public function itemTitle(string $itemTitle): self
    {
        if ($itemTitle === '') {
            throw new InvalidArgumentException(sprintf('The itemTitle of the block type "%s" is empty.', $this->id));
        }
        $this->itemTitle = $itemTitle;
        RequestCache::forgetInRequest($this);

        return $this;
    }

    /**
     * The block type in the protocol's definition, built once per request and locale (#246): a
     * block type given to several repeaters, such as those of each type of a resource or of the
     * blocks that hold blocks, is built once.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return RequestCache::rememberInRequest($this, 'definition', fn (): array => $this->buildDefinition());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefinition(): array
    {
        return array_filter([
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'description' => $this->description,
            'itemTitle' => $this->itemTitle,
            'fields' => array_map(fn (Field $field): array => $field->toDefinition(), $this->fields),
        ], fn (mixed $value): bool => $value !== null);
    }
}
