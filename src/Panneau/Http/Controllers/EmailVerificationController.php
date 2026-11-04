<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Notifications\Dispatcher as Notifications;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Panneau\Auth\AuthPages;
use Panneau\Auth\EmailVerification;
use Panneau\Auth\Notifications\VerifyEmail;

/**
 * `POST /auth/email/verification` and `POST /auth/email/verify` of the `panneau` auth mode, with
 * `panneau.auth.email_verification` (panneau-js decision 0048). The link is sent with Panneau's
 * own notification, and opens the page of the app: the site's verification notification and its
 * URL are left alone. Verifying doesn't need a session: the token proves the address.
 *
 * @internal
 */
final class EmailVerificationController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Gate $gate,
        private readonly Config $config,
        private readonly Notifications $notifications,
        private readonly Events $events,
        private readonly EmailVerification $verification,
        private readonly AuthPages $pages,
    ) {}

    public function send(Request $request): Response
    {
        $guard = $this->config->get('panneau.auth.guard');
        $user = $this->auth->guard(is_string($guard) ? $guard : null)->user();
        if ($user === null) {
            throw new AuthenticationException;
        }
        if ($this->gate->forUser($user)->denies('viewPanneau')) {
            throw new AuthorizationException;
        }
        if ($this->verification->mustVerify($user)) {
            $this->notifications->send($user, new VerifyEmail($this->pages->url(AuthPages::VERIFY_EMAIL, [
                'token' => $this->verification->token($user),
            ])));
        }

        return new Response(status: 204);
    }

    public function verify(Request $request): Response
    {
        $token = (string) $request->validate(['token' => ['required', 'string']])['token'];
        $user = $this->verification->userOf($token);
        if ($user === null) {
            throw ValidationException::withMessages(['token' => __('This verification link is invalid or has expired.')]);
        }
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            $this->events->dispatch(new Verified($user));
        }

        return new Response(status: 204);
    }
}
