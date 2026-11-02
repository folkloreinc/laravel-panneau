<?php

namespace Panneau\Actions;

/**
 * Replaces the built-in `show` link, which opens the item's panel, next to its list: a
 * `ShowAction::make()->url(...)` to the item on the site. Shown on the items that can be viewed.
 */
class ShowAction extends BuiltInLink
{
    public function id(): string
    {
        return 'show';
    }
}
