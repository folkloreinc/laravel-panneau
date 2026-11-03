<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use LogicException;
use Panneau\Auth\Passkeys;
use Panneau\Auth\PasswordConfirmation;
use Panneau\Definition\DefinitionBuilder;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Passkeys of the `panneau` auth mode, with `panneau.auth.passkeys` (panneau-js decision 0051),
 * on `laravel/passkeys` without its routes: the login with a passkey, which doesn't ask for the
 * second factor, and the passkeys of the account. The options of a ceremony wait in the session
 * for its second request.
 *
 * @internal
 */
final class PasskeyController
{
    private const LOGIN_OPTIONS = 'panneau.auth.passkey_login_options';

    private const REGISTRATION_OPTIONS = 'panneau.auth.passkey_registration_options';

    public function __construct(
        private readonly Auth $auth,
        private readonly Gate $gate,
        private readonly Config $config,
        private readonly Passkeys $passkeys,
        private readonly PasswordConfirmation $confirmation,
        private readonly DefinitionBuilder $definition,
    ) {}

    public function loginOptions(Request $request): JsonResponse
    {
        $options = $this->passkeys->loginOptions();
        if ($request->hasSession()) {
            $request->session()->put(self::LOGIN_OPTIONS, $options['serialized']);
        }

        return new JsonResponse(['options' => $options['browser']]);
    }

    public function login(Request $request): JsonResponse
    {
        $input = $request->validate(['credential' => ['required', 'array'], 'remember' => ['sometimes', 'boolean']]);
        $options = $request->hasSession() ? $request->session()->pull(self::LOGIN_OPTIONS) : null;
        if (! is_string($options)) {
            throw InvalidPasskeyException::make('The login has expired. Try again.');
        }
        $passkey = $this->passkeys->verify($input['credential'], $options);

        // The user of the guard, which may not be the user model of laravel/passkeys
        $guard = $this->guard();
        $user = $guard->getProvider()->retrieveById($passkey->getAttribute('user_id'));
        if ($user === null || $this->gate->forUser($user)->denies('viewPanneau')) {
            throw InvalidPasskeyException::make('Unable to sign in with this account.');
        }
        $guard->login($user, ($input['remember'] ?? false) === true);
        $request->session()->regenerate();
        // The passkey verified the user
        $this->confirmation->confirm($request);

        return new JsonResponse(['user' => $this->definition->user($user)]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return new JsonResponse([
            'data' => $user instanceof PasskeyUser
                ? $user->passkeys()->latest()->get()->map(fn (Passkey $passkey): array => $this->present($passkey))->values()->all()
                : [],
        ]);
    }

    public function registrationOptions(Request $request): JsonResponse
    {
        $user = $this->passkeyUser($request);
        $options = $this->passkeys->registrationOptions($user);
        if ($request->hasSession()) {
            $request->session()->put(self::REGISTRATION_OPTIONS, $options['serialized']);
        }

        return new JsonResponse(['options' => $options['browser']]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate(['name' => ['required', 'string', 'max:255'], 'credential' => ['required', 'array']]);
        $user = $this->passkeyUser($request);
        $options = $request->hasSession() ? $request->session()->pull(self::REGISTRATION_OPTIONS) : null;
        if (! is_string($options)) {
            throw InvalidPasskeyException::make('The registration has expired. Try again.');
        }
        $passkey = $this->passkeys->store($user, (string) $input['name'], $input['credential'], $options);

        return new JsonResponse(['data' => $this->present($passkey)], 201);
    }

    public function destroy(Request $request, string $passkey): Response
    {
        $user = $this->user($request);
        $found = $user instanceof PasskeyUser ? $user->passkeys()->whereKey($passkey)->first() : null;
        if (! $found instanceof Passkey) {
            throw new NotFoundHttpException;
        }
        app(DeletePasskey::class)($user, $found);

        return new Response(status: 204);
    }

    /**
     * @return array{id: string, name: string, authenticator: string|null, createdAt: string, lastUsedAt: string|null}
     */
    private function present(Passkey $passkey): array
    {
        $authenticator = $passkey->getAttribute('authenticator');

        return [
            'id' => (string) $passkey->getKey(),
            'name' => (string) $passkey->getAttribute('name'),
            'authenticator' => is_string($authenticator) ? $authenticator : null,
            'createdAt' => (string) $passkey->created_at?->toIso8601String(),
            'lastUsedAt' => $passkey->last_used_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            throw new LogicException('The passkey routes need the user of the panneau.auth middleware.');
        }

        return $user;
    }

    private function passkeyUser(Request $request): PasskeyUser&Authenticatable
    {
        $user = $this->user($request);
        if (! $user instanceof PasskeyUser) {
            throw new ConflictHttpException(__('This account can\'t have passkeys.'));
        }

        return $user;
    }

    private function guard(): SessionGuard
    {
        $name = $this->config->get('panneau.auth.guard');
        $guard = $this->auth->guard(is_string($name) ? $name : null);
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Passkeys need a session guard: set panneau.auth.guard to one.');
        }

        return $guard;
    }
}
