<?php

namespace Panneau\Layout;

use Panneau\Forms\Condition;

/**
 * A node of a form layout. Containers have children; `Field` nodes reference fields by name.
 * Fields that no node references are appended at the end of the form.
 */
abstract class Node
{
    protected ?Condition $visibleWhen = null;

    protected ?int $width = null;

    /**
     * The protocol type: a built-in type, or a type provided by a plugin.
     */
    abstract public function type(): string;

    /**
     * @param  Condition|array<string, mixed>  $condition
     */
    public function visibleWhen(Condition|array $condition): static
    {
        $this->visibleWhen = $condition instanceof Condition ? $condition : Condition::fromArray($condition);

        return $this;
    }

    /**
     * Width on a 12-column grid, inside a `Row`.
     */
    public function width(int $width): static
    {
        $this->width = max(1, min(12, $width));

        return $this;
    }

    /**
     * @return list<Node>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * The names of the fields this node and its children reference.
     *
     * @return list<string>
     */
    public function fieldNames(): array
    {
        $names = [];
        foreach ($this->children() as $child) {
            array_push($names, ...$child->fieldNames());
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        $definition = ['type' => $this->type(), ...$this->properties()];
        if ($this->width !== null) {
            $definition['width'] = $this->width;
        }
        if ($this->visibleWhen !== null) {
            $definition['visibleWhen'] = $this->visibleWhen->toArray();
        }
        if (count($this->children()) > 0 || $this instanceof Container) {
            $definition['children'] = array_map(fn (Node $child): array => $child->toDefinition(), $this->children());
        }

        return $definition;
    }

    /**
     * Properties of the node's type.
     *
     * @return array<string, mixed>
     */
    protected function properties(): array
    {
        return [];
    }
}
