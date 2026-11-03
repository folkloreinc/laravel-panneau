<?php

namespace TestApp\Conformance;

use Panneau\Fields\Number;
use TestApp\Panneau\Resources\PostsResource;

/**
 * The posts of the fixture, with a field the user can't see, for the conformance kit's check of
 * field abilities (panneau-js decision 0046).
 */
class ConformancePostsResource extends PostsResource
{
    public function id(): string
    {
        return 'posts';
    }

    public function fields(): array
    {
        return [...parent::fields(), Number::make('team_id')->canSee(fn (): bool => false)];
    }
}
