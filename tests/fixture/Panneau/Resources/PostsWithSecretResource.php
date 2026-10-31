<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\Text;

/**
 * The posts, with their secret: a write-only secret text field.
 */
class PostsWithSecretResource extends PostsResource
{
    public function fields(): array
    {
        return [...parent::fields(), Text::make('secret')->secret()->writeOnly()];
    }
}
