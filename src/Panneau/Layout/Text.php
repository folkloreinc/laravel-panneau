<?php

namespace Panneau\Layout;

/**
 * A static help paragraph.
 */
final class Text extends Node
{
    public function __construct(private string $text) {}

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function type(): string
    {
        return 'text';
    }

    protected function properties(): array
    {
        return ['text' => $this->text];
    }
}
