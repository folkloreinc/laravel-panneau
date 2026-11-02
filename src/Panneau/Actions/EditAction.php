<?php

namespace Panneau\Actions;

/**
 * Replaces the built-in `edit` link, which opens the item's form:
 * `EditAction::make()->url('/editor/{id}')`. Shown on the items that can be updated.
 */
class EditAction extends BuiltInLink
{
    public function id(): string
    {
        return 'edit';
    }
}
