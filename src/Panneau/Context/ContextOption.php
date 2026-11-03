<?php

namespace Panneau\Context;

use InvalidArgumentException;
use LogicException;

/**
 * A choice of a param of the context: its value, its label, a description, and for a param of a
 * resource, the item.
 */
final readonly class ContextOption
{
    public function __construct(
        public string $value,
        public string $label,
        public ?string $description = null,
        public ?object $item = null,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('A choice of the context needs a value.');
        }
    }

    /**
     * Choices as `ContextParam::options()` takes them: labels keyed by value, or a list of
     * `['value' => …, 'label' => …, 'description' => …]` or `ContextOption`.
     *
     * @param  array<array-key, mixed>  $options
     * @return list<self>
     */
    public static function list(array $options, string $param): array
    {
        $list = [];
        foreach ($options as $key => $option) {
            $list[] = match (true) {
                $option instanceof self => $option,
                is_array($option) => self::fromArray($option, $param),
                is_string($option) || is_int($option) => new self((string) $key, (string) $option),
                default => throw new LogicException(sprintf('The context param "%s" has a choice that is a %s.', $param, get_debug_type($option))),
            };
        }

        return $list;
    }

    /**
     * @param  array<array-key, mixed>  $option
     */
    public static function fromArray(array $option, string $param, ?string $value = null): self
    {
        $value ??= is_string($option['value'] ?? null) || is_int($option['value'] ?? null) ? (string) $option['value'] : null;
        $label = $option['label'] ?? null;
        $description = $option['description'] ?? null;
        if ($value === null || $value === '' || ! is_string($label) || ($description !== null && ! is_string($description))) {
            throw new LogicException(sprintf('The context param "%s" has a choice without a value or a label: %s.', $param, json_encode($option)));
        }

        return new self($value, $label, $description);
    }

    /**
     * The choice in the protocol's shape.
     *
     * @return array{value: string, label: string, description?: string}
     */
    public function toArray(): array
    {
        $option = ['value' => $this->value, 'label' => $this->label];
        if ($this->description !== null) {
            $option['description'] = $this->description;
        }

        return $option;
    }
}
