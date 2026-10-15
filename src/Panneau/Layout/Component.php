<?php

namespace Panneau\Layout;

/**
 * A component rendered by a plugin.
 */
final class Component extends Node
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(private string $component, private array $props = []) {}

    /**
     * @param  array<string, mixed>  $props
     */
    public static function make(string $component, array $props = []): self
    {
        return new self($component, $props);
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
