<?php

namespace TestApp\Panneau\Actions;

use Illuminate\Support\Collection;
use Panneau\Actions\Action;
use Panneau\Actions\ActionRefresh;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionScope;
use Panneau\Actions\ActionValues;
use Panneau\Fields\Text;

/**
 * A selection action that answers dry runs, with a required parameter. Authorized by the
 * policy's `update` method.
 */
class ArchivePosts extends Action
{
    public function scope(): array
    {
        return [ActionScope::Selection];
    }

    public function supportsDryRun(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [Text::make('reason')->required()];
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        foreach ($items as $post) {
            $post->status = 'archived';
            $post->save();
        }

        return $result->refresh(ActionRefresh::Items);
    }
}
