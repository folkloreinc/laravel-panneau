<?php

namespace Panneau\Layout;

/**
 * A component rendered by a plugin. Its children are rendered and given to it; without the
 * plugin, clients show them (panneau-js decision 0065).
 */
final class Component extends Node
{
    /**
     * @param  array<string, mixed>  $props
     * @param  list<Node>  $children
     */
    public function __construct(private string $component, private array $props = [], private array $children = []) {}

    /**
     * @param  array<string, mixed>  $props
     * @param  list<Node>  $children
     */
    public static function make(string $component, array $props = [], array $children = []): self
    {
        return new self($component, $props, $children);
    }

    public function children(): array
    {
        return $this->children;
    }

    public function type(): string
    {
        return 'component';
    }

    protected function properties(): array
    {
        return array_filter([
            'component' => $this->component,
            'props' => count($this->props) > 0 ? $this->props : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
