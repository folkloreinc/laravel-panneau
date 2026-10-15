<?php

namespace TestApp\Panneau\Actions;

use Illuminate\Support\Collection;
use Panneau\Actions\Action;
use Panneau\Actions\ActionPlacement;
use Panneau\Actions\ActionRefresh;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionValues;
use Panneau\Actions\Confirm;
use Panneau\Fields\Boolean;
use Panneau\Fields\Text;

class PublishPosts extends Action
{
    public function icon(): ?string
    {
        return 'send';
    }

    public function placement(): ?array
    {
        return [ActionPlacement::Row, ActionPlacement::Toolbar];
    }

    public function confirm(): Confirm
    {
        return Confirm::make('Publish {count, plural, one {# post} other {# posts}}?')->confirmLabel('Publish');
    }

    public function fields(): array
    {
        return [Boolean::make('notify')->label('Notify subscribers'), Text::make('note')->max(10)];
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        foreach ($items as $post) {
            if ($post->title === '') {
                $result->failed($post, 'The title is required.');

                continue;
            }
            $post->status = 'published';
            $post->body = $values->boolean('notify') ? 'Notified' : $post->body;
            $post->save();
        }

        return $result->message($items->count().' posts published.')->refresh(ActionRefresh::Items);
    }
}
