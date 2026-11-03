<?php

namespace TestApp\Roles;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Roles\OwnedResource;
use TestApp\Models\Post;
use TestApp\Panneau\Actions\PublishPosts;

/**
 * Contests, on the posts of the fixture: each one belongs to the team of its client.
 */
class ContestsResource extends Resource implements OwnedResource
{
    public function id(): string
    {
        return 'contests';
    }

    public function model(): string
    {
        return Post::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
            Text::make('body')->canSee('contests.budget.view'),
        ];
    }

    public function actions(): array
    {
        return [PublishPosts::class];
    }

    public function ownerScope(Authenticatable $user): array
    {
        return ['team_id' => $user->team_id ?? null];
    }
}
