<?php

namespace TestApp\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/**
 * A user of the skeleton's `users` table that verifies its email address, and can have
 * two-factor authentication (with Fortify's columns) and passkeys.
 */
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime'];
    }
}
