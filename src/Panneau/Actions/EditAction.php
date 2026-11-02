<?php

namespace Panneau\Actions;

/**
 * The built-in `edit` link: opens the item's form. Resources have it by default (see
 * `Resource::actions()`). Hidden on items that can't be updated (`abilities.update`).
 */
class EditAction extends BuiltInLink
{
    public function id(): string
    {
        return 'edit';
    }
}
