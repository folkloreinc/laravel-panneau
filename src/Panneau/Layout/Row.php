<?php

namespace Panneau\Layout;

final class Row extends Container
{
    /**
     * @param  list<Node>  $children
     */
    public static function make(array $children = []): self
    {
        return new self($children);
    }

    public function type(): string
    {
        return 'row';
    }
}
