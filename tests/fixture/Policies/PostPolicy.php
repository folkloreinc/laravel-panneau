<?php

namespace TestApp\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use TestApp\Models\Post;

class PostPolicy
{
    /**
     * Abilities denied by the current test.
     *
     * @var list<string>
     */
    public static array $denied = [];

    public function viewAny(Authenticatable $user): bool
    {
        return ! in_array('viewAny', self::$denied, true);
    }

    public function create(Authenticatable $user): bool
    {
        return ! in_array('create', self::$denied, true);
    }

    public function view(Authenticatable $user, Post $post): bool
    {
        return ! in_array('view', self::$denied, true);
    }

    public function update(Authenticatable $user, Post $post): bool
    {
        return ! in_array('update', self::$denied, true) && $post->title !== 'Locked';
    }

    public function delete(Authenticatable $user, Post $post): bool
    {
        return ! in_array('delete', self::$denied, true) && $post->title !== 'Locked';
    }

    public function publishPosts(Authenticatable $user, Post $post): bool
    {
        return ! in_array('publishPosts', self::$denied, true) && $post->status !== 'archived';
    }
}
