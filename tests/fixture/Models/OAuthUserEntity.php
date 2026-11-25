<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\PersonalAccessTokenResult;

/**
 * A user that isn't an Eloquent model, as a guard on a repository returns it: an entity with
 * methods, wrapping the model, that delegates Passport's tokens to it.
 */
class OAuthUserEntity implements OAuthenticatable
{
    public function __construct(private readonly OAuthUser $model) {}

    public function email(): string
    {
        return (string) $this->model->getAttribute('email');
    }

    public function getAuthIdentifierName(): string
    {
        return $this->model->getAuthIdentifierName();
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->model->getAuthIdentifier();
    }

    public function getAuthPasswordName(): string
    {
        return $this->model->getAuthPasswordName();
    }

    public function getAuthPassword(): string
    {
        return $this->model->getAuthPassword();
    }

    public function getRememberToken(): ?string
    {
        return $this->model->getRememberToken();
    }

    public function setRememberToken($value): void
    {
        $this->model->setRememberToken($value);
    }

    public function getRememberTokenName(): string
    {
        return $this->model->getRememberTokenName();
    }

    public function oauthApps(): MorphMany
    {
        return $this->model->oauthApps();
    }

    public function tokens(): HasMany
    {
        return $this->model->tokens();
    }

    public function tokenCan(string $scope): bool
    {
        return $this->model->tokenCan($scope);
    }

    public function tokenCant(string $scope): bool
    {
        return $this->model->tokenCant($scope);
    }

    public function createToken(string $name, array $scopes = []): PersonalAccessTokenResult
    {
        return $this->model->createToken($name, $scopes);
    }

    public function currentAccessToken(): ?ScopeAuthorizable
    {
        return $this->model->currentAccessToken();
    }

    public function withAccessToken(?ScopeAuthorizable $accessToken): static
    {
        $this->model->withAccessToken($accessToken);

        return $this;
    }

    public function getProviderName(): string
    {
        return $this->model->getProviderName();
    }
}
