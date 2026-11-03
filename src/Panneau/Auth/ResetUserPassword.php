<?php

namespace Panneau\Auth;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use LogicException;

/**
 * Sets the new password of a password reset, after Fortify's `ResetUserPassword` stub: the
 * password follows `Password::defaults()`, and is saved hashed on an Eloquent user. The default
 * of `panneau.auth.reset_password_using`: a site can point it to its own Fortify action, which
 * Fortify documents for users of `Illuminate\Foundation\Auth\User`.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(
        private readonly Validator $validator,
        private readonly Hasher $hasher,
    ) {}

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        $this->validator->make($input, [
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        if (! $user instanceof Model) {
            throw new LogicException('The default password reset saves Eloquent users: set panneau.auth.reset_password_using to an action that resets the password of yours.');
        }
        $user->forceFill(['password' => $this->hasher->make((string) $input['password'])])->save();
    }
}
