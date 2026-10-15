<?php

namespace Panneau\Lists\Filters;

/**
 * A filter of a list, sent as `filter[id]`. The repository receives the normalized value,
 * keyed by the filter's id.
 *
 * @phpstan-consistent-constructor
 */
abstract class Filter
{
    protected ?string $label = null;

    protected bool $hasDefault = false;

    protected mixed $default = null;

    public function __construct(protected string $id) {}

    public static function make(string $id): static
    {
        return new static($id);
    }

    /**
     * The protocol type: `text`, `select`, `toggle`, `date`, `relation`, or a plugin's.
     */
    abstract public function type(): string;

    /**
     * Turns the value from the query string (a string, or a list of strings) into the value
     * given to the repository. Returns null to ignore an invalid value.
     */
    abstract public function normalize(mixed $value): mixed;

    public function id(): string
    {
        return $this->id;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        $definition = ['id' => $this->id, 'type' => $this->type()];
        if ($this->label !== null) {
            $definition['label'] = $this->label;
        }
        if ($this->hasDefault) {
            $definition['default'] = $this->default;
        }

        return array_merge($definition, $this->typeDefinition());
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeDefinition(): array
    {
        return [];
    }
}
