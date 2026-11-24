<?php

namespace Panneau\Console;

use Illuminate\Auth\AuthManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use LogicException;
use Panneau\Agents\Connections;
use Panneau\Auth\EmailVerification;
use Panneau\Auth\TwoFactor;
use Panneau\Context\ContextParam;
use Panneau\Context\ContextRegistry;
use Panneau\Http\Middleware\AuthorizeAgents;

/**
 * Creates a personal token of a user for the MCP server of the agents (decision 0025), with
 * `Panneau\Agents\Connections` as the account page does (decision 0029): `mcp:use`, and
 * `panneau:write` with `--write`. The token is made by `createToken()` of the user's model
 * (Sanctum's `HasApiTokens`, or Passport's), and shown once.
 * It expires after `--days`, or `panneau.agents.mcp.token_lifetime`, through Sanctum's own expiry
 * (decision 0027): Sanctum's guard refuses it after that. Passport sets the lifetime of its
 * personal tokens itself, so a lifetime asked for here is refused with a Passport model.
 * A user the MCP server would refuse, whose address isn't verified or who must set up two-factor
 * authentication, gets no token.
 *
 *     php artisan panneau:agent-token ada@example.com
 */
final class AgentTokenCommand extends Command
{
    protected $signature = 'panneau:agent-token
        {user : The id or the email of the user}
        {--write : Let the agents use the tools that write}
        {--name=Panneau MCP : The name of the token}
        {--days= : Days until the token expires, 0 for never (default: panneau.agents.mcp.token_lifetime)}';

    protected $description = 'Create a personal token of a user for the MCP server of the agents';

    public function handle(AuthManager $auth, ContextRegistry $context, EmailVerification $verification, TwoFactor $twoFactor, Connections $connections): int
    {
        $config = $this->laravel['config'];
        try {
            $expiresAt = $connections->expiry($this->option('days'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $guard = $config->get('panneau.auth.guard');
        $provider = $auth->createUserProvider($config->get('auth.guards.'.(is_string($guard) ? $guard : $auth->getDefaultDriver()).'.provider'));
        $id = (string) $this->argument('user');
        $user = $provider === null ? null : (str_contains($id, '@')
            ? $provider->retrieveByCredentials(['email' => $id])
            : $provider->retrieveById($id));
        if (! $user instanceof Authenticatable) {
            $this->components->error(sprintf('No user "%s".', $id));

            return self::FAILURE;
        }
        if ($verification->mustVerify($user)) {
            $this->components->error(sprintf('The email address of "%s" is not verified: they verify it in Panneau first.', $id));

            return self::FAILURE;
        }
        if ($twoFactor->mustSetUp($user)) {
            $this->components->error(sprintf('"%s" must set up two-factor authentication: they set it up in Panneau first.', $id));

            return self::FAILURE;
        }
        try {
            $token = $connections->createToken($user, (string) $this->option('name'), (bool) $this->option('write'), $expiresAt);
        } catch (LogicException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $abilities = $token->connection->write ? [AuthorizeAgents::USE, AuthorizeAgents::WRITE] : [AuthorizeAgents::USE];

        $path = trim((string) $config->get('panneau.path', 'panneau'), '/').'/'.trim((string) $config->get('panneau.agents.mcp.path', 'mcp'), '/');
        $this->components->info('The token is shown once: keep it now.');
        $this->components->twoColumnDetail('Server', url($path));
        if ($context->declared()) {
            // The params can depend on the user
            $names = array_map(fn (ContextParam $param): string => $param->name(), $context->params($user));
            $this->components->twoColumnDetail('Context', sprintf(
                'followed by the context, as in the app: %s',
                url($path.'/'.implode('/', array_map(fn (string $name): string => $name.'/{'.$name.'}', $names))),
            ));
        }
        $this->components->twoColumnDetail('Abilities', implode(', ', $abilities));
        $this->components->twoColumnDetail('Expires', $expiresAt?->toDateTimeString() ?? 'never');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
