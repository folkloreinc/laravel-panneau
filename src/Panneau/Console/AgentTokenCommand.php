<?php

namespace Panneau\Console;

use Illuminate\Auth\AuthManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Http\Middleware\AuthorizeAgents;

/**
 * Creates a personal token of a user for the MCP server of the agents (decision 0025), until
 * the account page does: `mcp:use`, and `panneau:write` with `--write`. The token is made by
 * `createToken()` of the user's model (Sanctum's `HasApiTokens`, or Passport's), and shown once.
 *
 *     php artisan panneau:agent-token ada@example.com
 */
final class AgentTokenCommand extends Command
{
    protected $signature = 'panneau:agent-token
        {user : The id or the email of the user}
        {--write : Let the agents use the tools that write}
        {--name=Panneau MCP : The name of the token}';

    protected $description = 'Create a personal token of a user for the MCP server of the agents';

    public function handle(AuthManager $auth): int
    {
        $config = $this->laravel['config'];
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
        if (! method_exists($user, 'createToken')) {
            $this->components->error(sprintf('%s has no createToken(): use the HasApiTokens trait of laravel/sanctum (or laravel/passport).', $user::class));

            return self::FAILURE;
        }

        $abilities = $this->option('write') ? [AuthorizeAgents::USE, AuthorizeAgents::WRITE] : [AuthorizeAgents::USE];
        $result = $user->createToken((string) $this->option('name'), $abilities);
        // Sanctum's NewAccessToken, or Passport's PersonalAccessTokenResult
        $token = $result->plainTextToken ?? $result->accessToken ?? null;
        if (! is_string($token)) {
            $this->components->error('createToken() gave no token.');

            return self::FAILURE;
        }

        $path = trim((string) $config->get('panneau.path', 'panneau'), '/').'/'.trim((string) $config->get('panneau.agents.mcp.path', 'mcp'), '/');
        $this->components->info('The token is shown once: keep it now.');
        $this->components->twoColumnDetail('Server', url($path));
        $this->components->twoColumnDetail('Abilities', implode(', ', $abilities));
        $this->line($token);

        return self::SUCCESS;
    }
}
