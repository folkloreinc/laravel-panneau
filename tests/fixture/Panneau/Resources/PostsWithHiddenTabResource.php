<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\DateTime;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Layout\Field;
use Panneau\Layout\Tab;
use Panneau\Layout\Tabs;
use TestApp\Models\PostStatus;
use TestApp\Panneau\Actions\NotifyPosts;

/**
 * The posts, with a required field in a tab shown for published posts only, and an action with
 * a required parameter in a section shown when another is checked (#256).
 */
class PostsWithHiddenTabResource extends PostsResource
{
    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            Select::make('status')->options(PostStatus::class)->required(),
            DateTime::make('published_at')->required(),
        ];
    }

    public function layout(): array
    {
        return [
            Tabs::make([
                Tab::make('Content', [Field::make('title'), Field::make('status')]),
                Tab::make('Publication', [Field::make('published_at')])
                    ->visibleWhen(['field' => 'status', 'equals' => 'published']),
            ]),
        ];
    }

    public function actions(): array
    {
        return [...parent::actions(), NotifyPosts::class];
    }
}
