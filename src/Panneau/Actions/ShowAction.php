<?php

namespace Panneau\Actions;

/**
 * Replaces the built-in `show` link, which opens the item's panel, next to its list: with
 * another `label()` or `placement()`. Shown on the items that can be viewed, only when the
 * resource opens its items in the panel (`open()` returns `panel`). For a link to the item on
 * the site, declare a link of its own, `LinkAction::make('site')->url(...)`.
 */
class ShowAction extends BuiltInLink
{
    public function id(): string
    {
        return 'show';
    }
}
