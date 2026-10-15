<?php

namespace Panneau\Layout;

final class Sidebar extends Container
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
        return 'sidebar';
    }
}
