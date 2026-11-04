<?php

namespace Panneau\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Notifications\Dispatcher as Notifications;
use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use LogicException;
use Panneau\Auth\Notifications\VerifyEmail;

/**
 * Saves the name and the email of the account page, after Fortify's `UpdateUserProfileInformation`
 * stub, on an Eloquent user whose email is unique in its table. With
 * `panneau.auth.email_verification`, a new email of a `MustVerifyEmail` user is unverified, and
 * gets Panneau's verification link. The default of `panneau.auth.update_profile_using`: a site
 * can point it to its own Fortify action, which sends its own verification email.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private readonly Validator $validator,
        private readonly EmailVerification $verification,
        private readonly Notifications $notifications,
        private readonly AuthPages $pages,
    ) {}

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        if (! $user instanceof Model || ! $user instanceof Authenticatable) {
            throw new LogicException('The default account update saves Eloquent users: set panneau.auth.update_profile_using to an action that updates yours.');
        }
        $this->validator->make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
        ])->validate();

        $changed = $input['email'] !== $user->getAttribute('email');
        $user->forceFill(['name' => $input['name'], 'email' => $input['email']]);
        $verify = $changed && $user instanceof MustVerifyEmail && $this->verification->enabled();
        if ($verify) {
            $user->forceFill(['email_verified_at' => null]);
        }
        $user->save();
        if ($verify) {
            $this->notifications->send($user, new VerifyEmail($this->pages->url(AuthPages::VERIFY_EMAIL, [
                'token' => $this->verification->token($user),
            ])));
        }
    }
}
