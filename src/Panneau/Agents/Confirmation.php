<?php

namespace Panneau\Agents;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Carbon;
use Panneau\Context\Context;

/**
 * The confirmation of an action that `run_action` asks for before running it (decision 0022):
 * a token signed with the key of the app, for one user, one connection, the values of the
 * current context, and the exact arguments it previewed, valid for 5 minutes. Nothing is stored: the token holds its payload,
 * base64url of JSON, and the HMAC of that JSON.
 *
 * @internal
 */
final class Confirmation
{
    public const TTL = 300;

    public function __construct(
        private readonly Encrypter $encrypter,
        private readonly Container $container,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $selection
     * @param  array<array-key, mixed>  $values
     */
    public function sign(?Authenticatable $user, ?AgentCaller $agent, string $resource, string $action, ?array $selection, array $values): string
    {
        $payload = (string) json_encode([
            ...$this->claims($user, $agent, $resource, $action, $selection, $values),
            'expires' => Carbon::now()->getTimestamp() + self::TTL,
        ]);

        return self::base64url($payload).'.'.$this->signature($payload);
    }

    /**
     * Whether a token was signed for these arguments, and hasn't expired.
     *
     * @param  array<array-key, mixed>|null  $selection
     * @param  array<array-key, mixed>  $values
     */
    public function verify(string $token, ?Authenticatable $user, ?AgentCaller $agent, string $resource, string $action, ?array $selection, array $values): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        $payload = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if ($payload === false || ! hash_equals($this->signature($payload), $parts[1])) {
            return false;
        }
        $claims = json_decode($payload, true);
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

    private function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->encrypter->getKey());
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
