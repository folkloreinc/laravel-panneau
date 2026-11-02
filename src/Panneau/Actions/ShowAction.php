<?php

namespace Panneau\Actions;

/**
 * The built-in `show` link: opens the item's panel, next to its list. Hidden on items that
 * can't be viewed (`abilities.view`), and left out by the app when the resource opens its items
 * in their form (`Resource::open()`).
 */
class ShowAction extends BuiltInLink
{
    public function id(): string
    {
        return 'show';
    }
}
