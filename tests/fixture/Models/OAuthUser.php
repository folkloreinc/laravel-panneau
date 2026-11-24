<?php

namespace TestApp\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * A user of the `users` table with Passport's tokens: Passport's trait conflicts with Sanctum's,
 * on User.
 */
class OAuthUser extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    use HasApiTokens;
    use TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime'];
    }
}
