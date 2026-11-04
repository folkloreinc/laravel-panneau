<?php

namespace Panneau\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use LogicException;
use Panneau\Auth\PasswordConfirmation;
use Panneau\Auth\UpdateUserPassword;
use Panneau\Auth\UpdateUserProfileInformation;
use Panneau\Definition\DefinitionBuilder;

/**
 * The account of the current user in the `panneau` auth mode (panneau-js decision 0050):
 * `GET` and `PATCH /account` (the name and the email, by the Fortify action of
 * `panneau.auth.update_profile_using`) and `PUT /account/password` (by that of
 * `panneau.auth.update_password_using`).
 *
 * @internal
 */
final class AccountController
{
    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly DefinitionBuilder $definition,
        private readonly PasswordConfirmation $confirmation,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->definition->user($this->user($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $input = ['name' => $request->input('name'), 'email' => $request->input('email')];
        $action = $this->action('update_profile_using', UpdateUserProfileInformation::class, UpdatesUserProfileInformation::class);
        if ($action instanceof UpdateUserProfileInformation) {
            $action->update($user, $input);
        } else {
            $action->update($this->fortifyUser($user), $input);
        }
        if ($user instanceof Model) {
            $user->refresh();
        }

        return new JsonResponse(['data' => $this->definition->user($user)]);
    }

    public function password(Request $request): Response
    {
        $user = $this->user($request);
        $password = $request->input('password');
        $input = [
            'current_password' => $request->input('currentPassword'),
            'password' => $password,
            // The app checked the confirmation: the rules of Fortify's actions ask for it
            'password_confirmation' => $password,
        ];
        $action = $this->action('update_password_using', UpdateUserPassword::class, UpdatesUserPasswords::class);
        try {
            if ($action instanceof UpdateUserPassword) {
                $action->update($user, $input);
            } else {
                $action->update($this->fortifyUser($user), $input);
            }
        } catch (ValidationException $exception) {
            // The protocol's names
            $errors = $exception->errors();
            if (isset($errors['current_password'])) {
                $errors['currentPassword'] = $errors['current_password'];
                unset($errors['current_password']);
            }
            throw ValidationException::withMessages($errors);
        }
        // The user just typed it
        $this->confirmation->confirm($request);

        return new Response(status: 204);
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            throw new LogicException('The account routes need the user of the panneau.auth middleware.');
        }

        return $user;
    }

    private function fortifyUser(Authenticatable $user): User
    {
        if (! $user instanceof User) {
            throw new LogicException(sprintf('Fortify\'s actions update users of %s: use Panneau\'s default, or an action for your users.', User::class));
        }

        return $user;
    }

    /**
     * @template T of object
     *
     * @param  class-string  $default
     * @param  class-string<T>  $contract
     * @return T
     */
    private function action(string $key, string $default, string $contract): object
    {
        $class = $this->config->get('panneau.auth.'.$key, $default);
        $action = $this->container->make(is_string($class) ? $class : $default);
        if (! $action instanceof $contract) {
            throw new LogicException(sprintf('panneau.auth.%s must implement %s.', $key, $contract));
        }

        return $action;
    }
}
