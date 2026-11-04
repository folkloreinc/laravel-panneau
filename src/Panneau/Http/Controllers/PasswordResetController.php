<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker as BasePasswordBroker;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Notifications\Dispatcher as Notifications;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use LogicException;
use Panneau\Auth\AuthPages;
use Panneau\Auth\Notifications\ResetPassword;
use Panneau\Auth\ResetUserPassword;

/**
 * `POST /auth/password/forgot` and `POST /auth/password/reset` of the `panneau` auth mode, with
 * `panneau.auth.password_reset` (panneau-js decision 0048), on the password broker of
 * `panneau.auth.passwords`.
 *
 * The link is sent with Panneau's own notification, to users that the `viewPanneau` gate allows
 * in, and opens the page of the app: the site's reset notification and its URL are left alone.
 * The answer is the same whether the address has an account or not. The new password is set by
 * the Fortify action of `panneau.auth.reset_password_using`, and the reset doesn't log in.
 *
 * @internal
 */
final class PasswordResetController
{
    /**
     * Name of the rate limiter of the password reset and of the email verification.
     */
    public const RATE_LIMITER = 'panneau-auth';

    public function __construct(
        private readonly PasswordBrokerFactory $passwords,
        private readonly Gate $gate,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Notifications $notifications,
        private readonly Events $events,
        private readonly AuthPages $pages,
    ) {}

    public function forgot(Request $request): Response
    {
        $email = (string) $request->validate(['email' => ['required', 'string', 'email']])['email'];

        // Unknown addresses, users the gate refuses and links sent too recently get the same answer
        $this->broker()->sendResetLink(['email' => $email], function (CanResetPassword $user, string $token): string {
            if ($user instanceof Authenticatable && $this->gate->forUser($user)->allows('viewPanneau')) {
                $this->notifications->send($user, new ResetPassword($token, $this->pages->url(AuthPages::RESET_PASSWORD, [
                    'token' => $token,
                    'email' => $user->getEmailForPasswordReset(),
                ])));
            }

            return PasswordBroker::RESET_LINK_SENT;
        });

        return new Response(status: 204);
    }

    public function reset(Request $request): Response
    {
        $input = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials = [
            'email' => (string) $input['email'],
            'password' => (string) $input['password'],
            'token' => (string) $input['token'],
        ];

        $status = $this->broker()->reset($credentials, function (CanResetPassword $user, string $password): void {
            if (! $user instanceof Authenticatable || $this->gate->forUser($user)->denies('viewPanneau')) {
                throw ValidationException::withMessages(['email' => trans(PasswordBroker::INVALID_TOKEN)]);
            }
            // The app checked the confirmation: the rules of Fortify's actions ask for it
            $input = ['password' => $password, 'password_confirmation' => $password];
            $action = $this->action();
            if ($action instanceof ResetUserPassword) {
                $action->reset($user, $input);
            } elseif ($user instanceof User) {
                $action->reset($user, $input);
            } else {
                throw new LogicException(sprintf('Fortify\'s %s actions reset users of %s: use Panneau\'s default, or an action for your users.', ResetsUserPasswords::class, User::class));
            }
            if (method_exists($user, 'setRememberToken') && method_exists($user, 'save')) {
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
            $this->events->dispatch(new PasswordReset($user));
        });
        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => trans($status)]);
        }

        return new Response(status: 204);
    }

    private function broker(): BasePasswordBroker
    {
        $name = $this->config->get('panneau.auth.passwords');
        $broker = $this->passwords->broker(is_string($name) ? $name : null);
        if (! $broker instanceof BasePasswordBroker) {
            throw new LogicException('The password reset of Panneau needs a broker of the framework.');
        }

        return $broker;
    }

    private function action(): ResetsUserPasswords
    {
        $class = $this->config->get('panneau.auth.reset_password_using', ResetUserPassword::class);
        $action = $this->container->make(is_string($class) ? $class : ResetUserPassword::class);
        if (! $action instanceof ResetsUserPasswords) {
            throw new LogicException(sprintf('panneau.auth.reset_password_using must implement %s.', ResetsUserPasswords::class));
        }

        return $action;
    }
}
