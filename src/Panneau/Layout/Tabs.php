<?php

namespace Panneau\Layout;

final class Tabs extends Container
{
    /**
     * @param  list<Tab>  $tabs
     */
    public function __construct(array $tabs = [])
    {
        parent::__construct($tabs);
    }

    /**
     * @param  list<Tab>  $tabs
     */
    public static function make(array $tabs = []): self
    {
        return new self($tabs);
    }

    public function type(): string
    {
        return 'tabs';
    }
}
