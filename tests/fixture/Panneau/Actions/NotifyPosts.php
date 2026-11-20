<?php

namespace TestApp\Panneau\Actions;

use Illuminate\Support\Collection;
use Panneau\Actions\Action;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionValues;
use Panneau\Fields\Boolean;
use Panneau\Fields\Text;
use Panneau\Layout\Field;
use Panneau\Layout\Section;

/**
 * An action with a required message in a section shown when `notify` is checked (#256). Puts
 * the message it received in the body of the posts.
 */
class NotifyPosts extends Action
{
    public function fields(): array
    {
        return [Boolean::make('notify'), Text::make('message')->required()];
    }

    public function layout(): array
    {
        return [
            Field::make('notify'),
            Section::make('Notification', [Field::make('message')])->visibleWhen(['field' => 'notify', 'equals' => true]),
        ];
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        foreach ($items as $post) {
            $post->body = $values->string('message', 'none');
            $post->save();
        }

        return $result;
    }
}
