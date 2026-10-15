<?php

namespace Panneau\Layout;

/**
 * Places a field of the form.
 */
final class Field extends Node
{
    public function __construct(private string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function type(): string
    {
        return 'field';
    }

    public function fieldNames(): array
    {
        return [$this->name];
    }

    protected function properties(): array
    {
        return ['name' => $this->name];
    }
}
