<?php

namespace Panneau\Layout;

final class Tab extends Container
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(private string $label, array $children = [])
    {
        parent::__construct($children);
    }

    /**
     * @param  list<Node>  $children
     */
    public static function make(string $label, array $children = []): self
    {
        return new self($label, $children);
    }

    public function type(): string
    {
        return 'tab';
    }

    protected function properties(): array
    {
        return ['label' => $this->label];
    }
}
