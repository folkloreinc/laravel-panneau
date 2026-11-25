<?php

namespace TestApp\Passport;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use TestApp\Models\OAuthUser;
use TestApp\Models\OAuthUserEntity;

/**
 * A provider on a repository of users that aren't models: entities that wrap them.
 */
class EntityUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $model = parent::retrieveById($identifier);

        return $model instanceof OAuthUser ? new OAuthUserEntity($model) : null;
    }
}
