<?php

namespace TestApp\Panneau\Actions;

use Illuminate\Support\Collection;
use Panneau\Actions\Action;
use Panneau\Actions\ActionRefresh;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionScope;
use Panneau\Actions\ActionValues;

/**
 * The inverse of `feature-posts`, offered nowhere: it only runs as its undo.
 */
class UnfeaturePosts extends Action
{
    protected bool $hidden = true;

    public function scope(): array
    {
        return [ActionScope::Item, ActionScope::Selection];
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        foreach ($items as $post) {
            $post->featured = false;
            $post->save();
        }

        return $result->refresh(ActionRefresh::Items);
    }
}
