<?php

namespace Panneau\Agents;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens as PassportTokens;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Laravel\Sanctum\HasApiTokens as SanctumTokens;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;
use Panneau\Http\Middleware\AuthorizeAgents;
use RuntimeException;

/**
 * The connections of a user to the MCP server (decisions 0022 and 0029), through the package of
 * the guard of `panneau.agents.mcp.guard`, with its own models:
 *
 * - Sanctum: each personal access token of the user with `mcp:use` or `panneau:write`, which
 *   hasn't expired. Revoking it deletes it.
 * - Passport: each OAuth client for which the user holds a token with one of those scopes that
 *   still works (not revoked, and not expired or with a refresh token that isn't), one connection
 *   per client; and each personal access token with one of those scopes. Revoking a client
 *   revokes all the user's access tokens, refresh tokens and authorization codes for it.
 *
 * Tokens of the project with other abilities, including `*`, aren't connections of the agents.
 * Neither package is required: their classes are only referenced once the guard names theirs.
 */
final class Connections
{
    public function __construct(private readonly Config $config) {}

    /**
     * The connections of a user, the newest first.
     *
     * @return list<Connection>
     */
    public function all(object $user): array
    {
        $connections = match ($this->package()) {
            'passport' => $this->passportConnections($user),
            'sanctum' => $this->sanctumConnections($user),
            default => [],
        };
        // The newest first, then by id
        usort($connections, fn (Connection $a, Connection $b): int => ($b->createdAt <=> $a->createdAt) ?: strnatcmp($b->id, $a->id));

        return $connections;
    }

    /**
     * A connection of the user by its id, or null when it isn't one of theirs.
     */
    public function find(object $user, string $id): ?Connection
    {
        foreach ($this->all($user) as $connection) {
            if (hash_equals($connection->id, $id)) {
                return $connection;
            }
        }

        return null;
    }

    /**
     * Revokes a connection of the user: false when it isn't one of theirs. The token stops
     * working at once.
     */
    public function revoke(object $user, string $id): bool
    {
        $connection = $this->find($user, $id);
        if ($connection === null) {
            return false;
        }
        [, $key] = explode(':', $connection->id, 2);

        match ($this->package()) {
            'passport' => $this->revokePassport($user, $connection->type, $key),
            'sanctum' => $this->revokeSanctum($user, $key),
            default => null,
        };

        return true;
    }

    /**
     * The expiry of a new personal token, from a number of days, or
     * `panneau.agents.mcp.token_lifetime` when null: null for never, and 0 days is never too.
     *
     * @throws InvalidArgumentException for anything but a whole number of days
     */
    public function expiry(mixed $days = null): ?Carbon
    {
        $days ??= $this->config->get('panneau.agents.mcp.token_lifetime');
        if ($days === null) {
            return null;
        }
        if (filter_var($days, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new InvalidArgumentException(sprintf('The lifetime of a token is a number of days, 0 for never: "%s" is not.', is_scalar($days) ? (string) $days : get_debug_type($days)));
        }

        return (int) $days === 0 ? null : Carbon::now()->addDays((int) $days);
    }

    /**
     * Whether a personal token can be made for the user: the user's model has `createToken()`
     * of the package of the guard of the MCP server, a lifetime in the config is one its package can store, and with Passport, a personal access
     * client exists for the user's provider, as Passport's `ClientRepository` resolves it. One
     * query with Passport, none with Sanctum: the definition asks it.
     */
    public function canCreateTokens(object $user): bool
    {
        if (! method_exists($user, 'createToken') || $this->refusedTokens($user) !== null) {
            return false;
        }
        try {
            $expiresAt = $this->expiry();
        } catch (InvalidArgumentException) {
            return false;
        }
        if ($expiresAt !== null && ! self::takesExpiry($user)) {
            return false;
        }
        if (! class_exists(Passport::class) || ! in_array(PassportTokens::class, class_uses_recursive($user), true) || ! method_exists($user, 'getProviderName')) {
            return true;
        }
        try {
            Container::getInstance()->make(ClientRepository::class)->personalAccessClient($user->getProviderName());
        } catch (RuntimeException|LogicException) {
            return false;
        }

        return true;
    }

    /**
     * Makes a personal token of the user for the MCP server, with `createToken()` of the user's
     * model (Sanctum's `HasApiTokens`, or Passport's): `mcp:use`, and `panneau:write` when it
     * writes. Sanctum stores the expiry; Passport sets the lifetime of its personal tokens
     * itself, so an expiry is refused with a Passport model.
     *
     * @throws LogicException when the user's model can't make the token, or makes one of the
     *                        other package than the guard of the MCP server
     */
    public function createToken(object $user, string $name, bool $write = false, ?DateTimeInterface $expiresAt = null): ConnectionToken
    {
        if (! method_exists($user, 'createToken')) {
            throw new LogicException(sprintf('%s has no createToken(): use the HasApiTokens trait of laravel/sanctum (or laravel/passport).', $user::class));
        }
        $refused = $this->refusedTokens($user);
        if ($refused !== null) {
            throw new LogicException($refused);
        }
        // Sanctum takes the expiry as its third argument; Passport has none
        if ($expiresAt !== null && ! self::takesExpiry($user)) {
            throw new LogicException(sprintf('%s::createToken() takes no expiry: with laravel/passport, the lifetime of personal tokens is set by Passport::personalAccessTokensExpireIn().', $user::class));
        }

        $abilities = $write ? [AuthorizeAgents::USE, AuthorizeAgents::WRITE] : [AuthorizeAgents::USE];
        try {
            $result = $expiresAt === null
                ? $user->createToken($name, $abilities)
                : $user->createToken($name, $abilities, $expiresAt);
        } catch (RuntimeException $exception) {
            // Passport without a personal access client: its message, and the command that makes one
            $message = $exception->getMessage();
            if ($user instanceof OAuthenticatable) {
                $message = sprintf('%s (php artisan passport:client --personal --provider=%s)', rtrim($message, '.'), $user->getProviderName());
            }

            throw new LogicException($message, previous: $exception);
        }
        // Sanctum's NewAccessToken (the model in accessToken), or Passport's
        // PersonalAccessTokenResult (the JWT in accessToken, the model by getToken())
        $plainText = is_object($result) ? ($result->plainTextToken ?? $result->accessToken ?? null) : null;
        $model = is_object($result) ? ($result->accessToken ?? null) : null;
        if (! $model instanceof Model && is_object($result) && method_exists($result, 'getToken')) {
            $model = $result->getToken();
        }
        if (! is_string($plainText) || ! $model instanceof Model) {
            throw new LogicException('createToken() gave no token.');
        }

        return new ConnectionToken($plainText, $this->tokenConnection($model));
    }

    /**
     * Whether `createToken()` of the user's model takes an expiry: Sanctum's third argument,
     * which Passport's hasn't.
     */
    private static function takesExpiry(object $user): bool
    {
        return (new \ReflectionMethod($user, 'createToken'))->getNumberOfParameters() >= 3;
    }

    /**
     * Why the MCP server would refuse the tokens of the user's model: its `HasApiTokens` is the
     * other package's than the guard's. Null when nothing says so.
     */
    private function refusedTokens(object $user): ?string
    {
        $package = $this->package();
        $traits = class_uses_recursive($user);
        $other = match (true) {
            $package === 'sanctum' && in_array(PassportTokens::class, $traits, true) => 'laravel/passport',
            $package === 'passport' && in_array(SanctumTokens::class, $traits, true) => 'laravel/sanctum',
            default => null,
        };

        return $other === null ? null : sprintf(
            '%s uses the HasApiTokens of %s, but panneau.agents.mcp.guard "%s" is a %s guard: the MCP server would refuse its tokens.',
            $user::class,
            $other,
            $this->guard(),
            $package,
        );
    }

    /**
     * The guard of the MCP server.
     */
    private function guard(): string
    {
        $guard = $this->config->get('panneau.agents.mcp.guard');

        return is_string($guard) && $guard !== '' ? $guard : 'sanctum';
    }

    /**
     * The package of the guard of the MCP server: `sanctum`, `passport`, or null.
     */
    private function package(): ?string
    {
        $driver = $this->config->get('auth.guards.'.$this->guard().'.driver');

        return match (true) {
            $driver === 'passport' && class_exists(Passport::class) => 'passport',
            $driver === 'sanctum' && class_exists(PersonalAccessToken::class) => 'sanctum',
            default => null,
        };
    }

    /**
     * Whether abilities or scopes are those of an agent's connection.
     */
    private static function forAgents(mixed $abilities): bool
    {
        return is_array($abilities) && (in_array(AuthorizeAgents::USE, $abilities, true) || in_array(AuthorizeAgents::WRITE, $abilities, true));
    }

    private static function writes(mixed $abilities): bool
    {
        return is_array($abilities) && in_array(AuthorizeAgents::WRITE, $abilities, true);
    }

    /**
     * The connection of a personal token of either package.
     */
    private function tokenConnection(Model $token): Connection
    {
        $abilities = $token->getAttribute('abilities') ?? $token->getAttribute('scopes');

        return new Connection(
            id: Connection::TOKEN.':'.$token->getKey(),
            type: Connection::TOKEN,
            name: (string) $token->getAttribute('name'),
            write: self::writes($abilities),
            createdAt: $token->getAttribute('created_at') ?? Carbon::now(),
            lastUsedAt: $token->getAttribute('last_used_at'),
            expiresAt: $token->getAttribute('expires_at') ?? $this->sanctumExpiration($token),
        );
    }

    /**
     * When `sanctum.expiration` ends a Sanctum token without an expiry of its own.
     */
    private function sanctumExpiration(Model $token): ?Carbon
    {
        $minutes = $this->config->get('sanctum.expiration');
        $created = $token->getAttribute('created_at');
        if (! $token instanceof PersonalAccessToken || ! is_numeric($minutes) || (int) $minutes <= 0 || ! $created instanceof DateTimeInterface) {
            return null;
        }

        return Carbon::instance($created)->addMinutes((int) $minutes);
    }

    /**
     * @return list<Connection>
     */
    private function sanctumConnections(object $user): array
    {
        if (! method_exists($user, 'tokens')) {
            return [];
        }
        $connections = [];
        foreach ($user->tokens()->get()->all() as $token) {
            if (! $token instanceof PersonalAccessToken || ! self::forAgents($token->getAttribute('abilities'))) {
                continue;
            }
            $connection = $this->tokenConnection($token);
            if ($connection->expiresAt === null || $connection->expiresAt > Carbon::now()) {
                $connections[] = $connection;
            }
        }

        return $connections;
    }

    private function revokeSanctum(object $user, string $key): void
    {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->whereKey($key)->delete();
        }
    }

    /**
     * @return list<Connection>
     */
    private function passportConnections(object $user): array
    {
        if (! $user instanceof OAuthenticatable) {
            return [];
        }
        $now = Carbon::now();
        /** @var array<string, non-empty-list<array{Token, Carbon|null}>> $byClient */
        $byClient = [];
        $connections = [];
        foreach (self::passportTokens($user)->with(['client', 'refreshToken'])->where('revoked', false)->get()->all() as $token) {
            if (! self::forAgents($token->getAttribute('scopes'))) {
                continue;
            }
            $client = $token->getRelationValue('client');
            if ($client instanceof Client && $client->hasGrantType('personal_access')) {
                $expires = self::date($token, 'expires_at');
                if ($expires === null || $expires->gt($now)) {
                    $connections[] = $this->tokenConnection($token);
                }

                continue;
            }
            // The client stays connected while a refresh token can renew its access
            $until = self::until($token);
            if ($until !== false && $client instanceof Client && $client->getAttribute('revoked') !== true) {
                $byClient[(string) $client->getKey()][] = [$token, $until];
            }
        }
        foreach ($byClient as $id => $tokens) {
            $ends = array_column($tokens, 1);
            $client = $tokens[0][0]->getRelationValue('client');
            $connections[] = new Connection(
                id: Connection::CLIENT.':'.$id,
                type: Connection::CLIENT,
                name: $client instanceof Client ? (string) $client->getAttribute('name') : '',
                write: array_filter($tokens, fn (array $token): bool => self::writes($token[0]->getAttribute('scopes'))) !== [],
                createdAt: min(array_map(fn (array $token): Carbon => self::date($token[0], 'created_at') ?? $now, $tokens)),
                // Passport doesn't record the use of a token
                lastUsedAt: null,
                expiresAt: in_array(null, $ends, true) ? null : max($ends),
            );
        }

        return $connections;
    }

    /**
     * Until when an OAuth token keeps a client connected: the later of the expiry of the access
     * token and of its refresh token, null for never, false when it is over.
     */
    private static function until(Token $token): Carbon|false|null
    {
        $end = self::date($token, 'expires_at');
        if ($end === null) {
            return null;
        }
        $refresh = $token->getRelationValue('refreshToken');
        if ($refresh instanceof Model && $refresh->getAttribute('revoked') !== true) {
            $refreshEnd = self::date($refresh, 'expires_at');
            if ($refreshEnd === null) {
                return null;
            }
            $end = $refreshEnd->max($end);
        }

        return $end->gt(Carbon::now()) ? $end : false;
    }

    private static function date(Model $model, string $key): ?Carbon
    {
        $value = $model->getAttribute($key);

        return $value instanceof DateTimeInterface ? Carbon::instance($value) : null;
    }

    /**
     * The OAuth tokens of the user: those of its id from the clients of its provider, or of no
     * provider, as Passport's guard accepts them. Not `$user->tokens()` of Passport, which keeps
     * the clients without a provider, those of the dynamic registration, only when the provider
     * of `auth.guards.api` is the user's.
     *
     * @return Builder<Token>
     */
    private static function passportTokens(OAuthenticatable $user): Builder
    {
        $provider = $user->getProviderName();

        return Passport::token()->newQuery()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereHas('client', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('provider')->orWhere('provider', $provider),
            ));
    }

    private function revokePassport(object $user, string $type, string $key): void
    {
        if (! $user instanceof OAuthenticatable) {
            return;
        }
        $tokens = self::passportTokens($user)->with('refreshToken');
        $tokens = $type === Connection::CLIENT ? $tokens->where('client_id', $key) : $tokens->whereKey($key);
        foreach ($tokens->get()->all() as $token) {
            $token->refreshToken?->revoke();
            $token->revoke();
        }
        if ($type === Connection::CLIENT) {
            Passport::authCode()->newQuery()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('client_id', $key)
                ->update(['revoked' => true]);
        }
    }
}
