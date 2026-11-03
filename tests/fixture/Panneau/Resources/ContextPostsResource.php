<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\Post;

/**
 * The posts of the author of the current context: without one, the `403` context problem.
 */
class ContextPostsResource extends Resource
{
    public function model(): string
    {
        return Post::class;
    }

    public function fields(): array
    {
        return [Text::make('title')->required()];
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1, 'author_id' => $this->context()->getOrFail('author')];
    }
}
