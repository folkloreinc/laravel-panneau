<?php

namespace TestApp\Panneau\Actions;

use Illuminate\Support\Collection;
use Panneau\Actions\Action;
use Panneau\Actions\ActionRefresh;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionScope;
use Panneau\Actions\ActionValues;

/**
 * An action undone by another (`unfeature-posts`): its result carries the `undo`, with the posts
 * it changed. Posts already featured are skipped as unchanged, so that the undo leaves them.
 */
class FeaturePosts extends Action
{
    protected ?string $undoneBy = 'unfeature-posts';

    public function scope(): array
    {
        return [ActionScope::Item, ActionScope::Selection];
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        foreach ($items as $post) {
            if ($post->featured) {
                $result->skipped($post);

                continue;
            }
            $post->featured = true;
            $post->save();
        }

        return $result->message(__('Featured.'))->refresh(ActionRefresh::Items);
    }
}
