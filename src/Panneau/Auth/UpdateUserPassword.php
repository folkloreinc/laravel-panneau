<?php

namespace Panneau\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use LogicException;

/**
 * Changes the password of the account page, after Fortify's `UpdateUserPassword` stub: the current
 * password, and a new one that follows `Password::defaults()`, saved hashed on an Eloquent user.
 * The default of `panneau.auth.update_password_using`: a site can point it to its own Fortify
 * action.
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private readonly Validator $validator,
        private readonly Hasher $hasher,
    ) {}

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        if (! $user instanceof Model || ! $user instanceof Authenticatable) {
            throw new LogicException('The default password change saves Eloquent users: set panneau.auth.update_password_using to an action that changes the password of yours.');
        }
        $this->validator->make($input, [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();
        if (! $this->hasher->check((string) $input['current_password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => __('The provided password does not match your current password.')]);
        }

        $user->forceFill(['password' => $this->hasher->make((string) $input['password'])])->save();
    }
}
