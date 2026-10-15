<?php

namespace Panneau\Layout;

/**
 * A node with children.
 */
abstract class Container extends Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(protected array $children = []) {}

    public function children(): array
    {
        return $this->children;
    }
}
