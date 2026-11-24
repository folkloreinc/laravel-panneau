<?php

namespace Panneau\Agents;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Support\Carbon;
use Panneau\Context\Context;

/**
 * The confirmation of an action that `run_action` asks for before running it (decision 0022):
 * a token for one user, one connection, the values of the current context, and the exact
 * arguments it previewed, valid for 5 minutes. Nothing is stored: the token is the JSON of its
 * claims, encrypted by Laravel's encrypter with the key of the app (decision 0027), so the agent
 * can neither read nor change it.
 *
 * @internal
 */
final class Confirmation
{
    public const TTL = 300;

    public function __construct(
        private readonly StringEncrypter $encrypter,
        private readonly Container $container,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $selection
     * @param  array<array-key, mixed>  $values
     */
    public function sign(?Authenticatable $user, ?AgentCaller $agent, string $resource, string $action, ?array $selection, array $values): string
    {
        return $this->encrypter->encryptString((string) json_encode([
            ...$this->claims($user, $agent, $resource, $action, $selection, $values),
            'expires' => Carbon::now()->getTimestamp() + self::TTL,
        ]));
    }

    /**
     * Whether a token was made for these arguments, and hasn't expired.
     *
     * @param  array<array-key, mixed>|null  $selection
     * @param  array<array-key, mixed>  $values
     */
    public function verify(string $token, ?Authenticatable $user, ?AgentCaller $agent, string $resource, string $action, ?array $selection, array $values): bool
    {
        try {
            $claims = json_decode($this->encrypter->decryptString($token), true);
        } catch (DecryptException) {
            return false;
        }
        if (! is_array($claims) || ! is_int($claims['expires'] ?? null) || $claims['expires'] < Carbon::now()->getTimestamp()) {
            return false;
        }
        unset($claims['expires']);

        return $claims === $this->claims($user, $agent, $resource, $action, $selection, $values);
    }

    /**
     * @param  array<array-key, mixed>|null  $selection
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function claims(?Authenticatable $user, ?AgentCaller $agent, string $resource, string $action, ?array $selection, array $values): array
    {
        $identifier = $user?->getAuthIdentifier();

        return [
            'user' => is_int($identifier) || is_string($identifier) ? (string) $identifier : null,
            'agent' => $agent?->id,
            // A confirmation made on one context is refused on another
            'context' => $this->container->make(Context::class)->all(),
            'resource' => $resource,
            'action' => $action,
            'arguments' => hash('sha256', (string) json_encode(self::canonical(['selection' => $selection, 'values' => $values]))),
        ];
    }

    /**
     * The arguments with their keys sorted, at every depth: the same arguments in another
     * order are the same.
     */
    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map(self::canonical(...), $value);
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
