<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as ProviderUser;
use LogicException;
use Panneau\Auth\PasswordConfirmation;
use Panneau\Contracts\Auth\ResolvesProviderUsers;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Logins with the OAuth providers of `panneau.auth.providers` (panneau-js decision 0052), with
 * Socialite: `GET /auth/providers/{provider}/redirect` and its callback.
 *
 * The callback needs a verified email (Google's `email_verified`), in an allowed domain when the
 * provider has `domains` (and Google's `hd` claim in it too), and the user of the guard with this
 * address, or the one that `panneau.auth.provider_users` resolves, allowed in by the gate. It
 * doesn't ask for the second factor. It goes to `next`, a local path under the path of the app,
 * or back to the app with `authError` and a code.
 *
 * @internal
 */
final class ProviderController
{
    private const NEXT = 'panneau.auth.provider_next';

    public function __construct(
        private readonly Auth $auth,
        private readonly Gate $gate,
        private readonly Config $config,
        private readonly Container $container,
        private readonly UrlGenerator $url,
        private readonly PasswordConfirmation $confirmation,
    ) {}

    public function redirect(Request $request, string $provider): Response
    {
        $this->ensureConfigured($provider);
        $next = $request->query('next');
        if ($request->hasSession()) {
            $request->session()->put(self::NEXT, is_string($next) && $this->isLocal($next) ? $next : null);
        }
        $driver = $this->driver($provider);
        // Without a session (a token), nothing would keep the state: the callback can't log in anyway
        if (! $request->hasSession() && method_exists($driver, 'stateless')) {
            $driver->stateless();
        }

        $redirect = $driver->redirect();

        return $redirect instanceof RedirectResponse ? $this->redirectTo($redirect->getTargetUrl()) : $redirect;
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureConfigured($provider);
        try {
            $user = $this->driver($provider)->user();
        } catch (Throwable) {
            return $this->failed('provider');
        }
        $email = $user->getEmail();
        $raw = $this->raw($user);
        if (! is_string($email) || $email === '' || ($provider === 'google' && ($raw['email_verified'] ?? false) !== true)) {
            return $this->failed('email');
        }
        if (! $this->inDomains($provider, $email, $raw)) {
            return $this->failed('domain');
        }

        $guard = $this->guard();
        $found = $guard->getProvider()->retrieveByCredentials(['email' => $email]);
        $resolver = $this->config->get('panneau.auth.provider_users');
        $resolved = is_string($resolver)
            ? $this->resolver($resolver)->resolve($provider, $user, $found)
            : $found;
        if (! $resolved instanceof Authenticatable || $this->gate->forUser($resolved)->denies('viewPanneau')) {
            return $this->failed('access');
        }

        $guard->login($resolved);
        $request->session()->regenerate();
        $this->confirmation->confirm($request);
        $next = $request->session()->pull(self::NEXT);

        return $this->redirectTo(is_string($next) && $this->isLocal($next) ? $next : $this->appPath());
    }

    /**
     * Back to the app, with the reason of the failure (panneau-js `AUTH_ERROR_CODES`).
     */
    private function failed(string $code): RedirectResponse
    {
        return $this->redirectTo($this->appPath().'?authError='.$code);
    }

    /**
     * A redirection without the page that Symfony writes in its body: a browser follows it.
     */
    private function redirectTo(string $url): RedirectResponse
    {
        $response = new RedirectResponse($url);
        $response->setContent('');

        return $response;
    }

    private function ensureConfigured(string $provider): void
    {
        $providers = $this->config->get('panneau.auth.providers', []);
        if (! is_array($providers) || ! array_key_exists($provider, $providers)) {
            throw new NotFoundHttpException;
        }
    }

    private function driver(string $id): Provider
    {
        if (! $this->container->bound(Socialite::class)) {
            throw new LogicException('OAuth providers need laravel/socialite: composer require laravel/socialite.');
        }
        $settings = $this->settings($id);
        $driver = $this->container->make(Socialite::class)->driver(is_string($settings['driver'] ?? null) ? $settings['driver'] : $id);
        if (method_exists($driver, 'redirectUrl')) {
            $driver->redirectUrl($this->url->route('panneau.api.auth.providers.callback', ['provider' => $id]));
        }
        $scopes = $settings['scopes'] ?? [];
        if (is_array($scopes) && $scopes !== [] && method_exists($driver, 'scopes')) {
            $driver->scopes($scopes);
        }
        $with = is_array($settings['with'] ?? null) ? $settings['with'] : [];
        $domains = $this->domains($id);
        // Google shows the accounts of the domain only
        if ($id === 'google' && count($domains) === 1) {
            $with += ['hd' => $domains[0]];
        }
        if ($with !== [] && method_exists($driver, 'with')) {
            $driver->with($with);
        }

        return $driver;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(string $id): array
    {
        $providers = $this->config->get('panneau.auth.providers', []);
        $settings = is_array($providers) ? ($providers[$id] ?? null) : null;

        return is_array($settings) ? $settings : [];
    }

    /**
     * @return list<string>
     */
    private function domains(string $id): array
    {
        $domains = $this->settings($id)['domains'] ?? [];

        return is_array($domains) ? array_values(array_map(fn (mixed $domain): string => strtolower((string) $domain), array_filter($domains, is_string(...)))) : [];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function inDomains(string $provider, string $email, array $raw): bool
    {
        $domains = $this->domains($provider);
        if ($domains === []) {
            return true;
        }
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if (! in_array($domain, $domains, true)) {
            return false;
        }

        // Google's Workspace domain, which an address of another domain can't claim
        return $provider !== 'google' || in_array(strtolower((string) ($raw['hd'] ?? '')), $domains, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(ProviderUser $user): array
    {
        $raw = method_exists($user, 'getRaw') ? $user->getRaw() : [];

        return is_array($raw) ? $raw : [];
    }

    private function resolver(string $class): ResolvesProviderUsers
    {
        $resolver = $this->container->make($class);
        if (! $resolver instanceof ResolvesProviderUsers) {
            throw new LogicException(sprintf('panneau.auth.provider_users must implement %s.', ResolvesProviderUsers::class));
        }

        return $resolver;
    }

    private function isLocal(string $path): bool
    {
        $base = $this->appPath();

        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            && ($path === $base || str_starts_with($path, rtrim($base, '/').'/') || str_starts_with($path, rtrim($base, '/').'?'));
    }

    private function appPath(): string
    {
        return '/'.trim((string) $this->config->get('panneau.path', 'panneau'), '/');
    }

    private function guard(): SessionGuard
    {
        $name = $this->config->get('panneau.auth.guard');
        $guard = $this->auth->guard(is_string($name) ? $name : null);
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('OAuth providers need a session guard: set panneau.auth.guard to one.');
        }

        return $guard;
    }
}
