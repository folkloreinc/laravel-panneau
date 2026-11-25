# Setting up the MCP server

This guide turns on the MCP server of the agents in an existing Laravel project that runs Panneau 5. AI clients (Claude, ChatGPT, Cursor, an IDE…) then read and change the content of Panneau as one of its users, with that user's permissions.

How the server works is in [architecture.md](architecture.md#agents) and in decisions [0022](decisions/0022-agents-on-laravel-mcp-and-laravel-ai.md), [0025](decisions/0025-five-tools-read-first.md) to [0030](decisions/0030-next-for-the-oauth-guest.md) and [0032](decisions/0032-upload-file-tool.md). This page is the order of the steps.

There are two ways for a client to sign in:

1. **A personal token**, with Sanctum: the user pastes a token into the client. Start here: it is the shortest path, and works with every client that can send a header.
2. **OAuth**, with Passport: the client registers itself and sends the user to a consent page. For the clients that can't take a pasted token.

Both need `laravel/mcp`. A project uses one token package for the server: see [Sanctum or Passport](#sanctum-or-passport).

## Before you start

- `folklore/laravel-panneau` 5.0.0-alpha.28 or later, with the `@panneau/*` version of the [table of the Readme](../Readme.md#installing-panneau-5): the connections of the account need `@panneau/app` 5.0.0-alpha.51 or later.
- The users of Panneau log in on a session guard (`panneau.auth.guard`).
- `APP_URL` is the URL of the site: `panneau:agent-token` prints the address of the server from it. Over HTTP, the addresses that Panneau and the OAuth metadata give follow the request: behind a load balancer or a proxy, [trust it](https://laravel.com/docs/requests#configuring-trusted-proxies) so that they have the right scheme and host.

## 1. Personal tokens with Sanctum

### Install

```bash
composer require laravel/mcp laravel/sanctum
php artisan vendor:publish --tag=sanctum-migrations
php artisan migrate
```

Skip the publication and the migration if the project already has Sanctum's `personal_access_tokens` table.

Put Sanctum's trait on the model of the users of Panneau (the model of the provider of `panneau.auth.guard`):

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

### Turn it on

In `config/panneau.php`, add the `agents` section if your published file doesn't have it (it came in 5.0.0-alpha.28), and turn the server on:

```php
'agents' => [
    'mcp' => [
        'enabled' => true,
        // 'guard' => 'sanctum',      // the default
        // 'token_lifetime' => 90,    // days until a token expires; null: never
    ],
],
```

Then register the route, in `routes/panneau.php`, after `Panneau::routes()`:

```php
Panneau::routes();
Panneau::mcpRoutes();
```

Keep `Panneau::mcpRoutes()` out of the `web` middleware group (`routes/web.php`): a client has no session and no CSRF token. `routes/panneau.php`, which `panneau:install` wrote and the `PanneauServiceProvider` loads, is outside it.

Who may use the agents: by default, everyone the `viewPanneau` gate lets in. To restrict them, define `usePanneauAgents` too:

```php
Gate::define('usePanneauAgents', fn (User $user): bool => $user->is_admin);
```

Check that the route is there:

```bash
php artisan route:list --path=mcp
```

### Make a token

A user makes their own tokens in their account, in the **Connections** section of the app (the `panneau` auth mode only). From the command line, for any user (an id or an email):

```bash
php artisan panneau:agent-token ada@example.com
```

It prints the address of the server, the abilities, the expiry and the token, once. `--write` lets the token use the tools that write (`save_item`, `run_action` and `upload_file`), `--days=30` sets its lifetime (`0`: never), `--name` its name. With a [context](architecture.md#current-context), the address is followed by its values, as the command shows: `/panneau/mcp/season/2026`.

### Connect a client

With Claude Code:

```bash
claude mcp add --transport http panneau https://example.com/panneau/mcp --header "Authorization: Bearer 1|abc…"
```

Other clients take the same two things: the address of the server (Streamable HTTP), and the `Authorization: Bearer …` header.

### Check

Without a client, list the tools with the token:

```bash
curl https://example.com/panneau/mcp \
  -H "Authorization: Bearer 1|abc…" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

A token that reads gets `describe`, `find_items` and `get_item`; with `--write`, `save_item`, `run_action` and `upload_file` too. Without a token, the answer is a `401`. In the app, the account lists the token under **Connections**, where the user revokes it.

## 2. OAuth with Passport

OAuth comes on top of the server above: do part 1 first, then move the server to a Passport guard. Read [Sanctum or Passport](#sanctum-or-passport) before you start: the user model can't keep Sanctum's tokens.

### Install

```bash
composer require laravel/passport:^13.0
php artisan vendor:publish --tag=passport-migrations
php artisan vendor:publish --tag=passport-config
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal --provider=users
```

- `passport:keys` writes the keys to `storage/`. In production, set them in `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` instead, or run the command on each server.
- `passport:client --personal` makes the client of the personal tokens, for the provider of your users (`users` here). Without it, the account offers no button to make a token, and `panneau:agent-token` says so. `passport:install` runs these steps too, and asks for the provider of that client: pick the users' one.

### The user model

Replace Sanctum's trait by Passport's, and implement its interface:

```php
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;
}
```

### The guards

In `config/auth.php`, a `passport` guard on the provider of the users of Panneau:

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'passport' => ['driver' => 'passport', 'provider' => 'users'],
],
```

No `api` guard is needed. In `config/passport.php`, `guard` is the session guard the users of Panneau log in on, the one of `panneau.auth.guard` (or the default guard when it is null):

```php
'guard' => 'web',
```

Passport's consent page uses it to see who is logged in: with another guard, the user is sent to the login again and again.

### Turn it on

In `config/panneau.php`:

```php
'agents' => [
    'mcp' => [
        'enabled' => true,
        'guard' => 'passport',
        'oauth' => [
            'enabled' => true,
            'access_token_lifetime' => 60, // minutes; the client refreshes it
            'refresh_token_lifetime' => null, // days; null: Passport's, a year
        ],
    ],
],
```

These two lifetimes are Passport's own: they apply to every Passport token of the application, not only to the agents'. A project that already uses Passport for its API leaves them null and sets them where it sets its own. Personal tokens don't take a lifetime with Passport (`Passport::personalAccessTokensExpireIn()` sets it): leave `token_lifetime` null, or the account offers no button and the command refuses.

`Panneau::mcpRoutes()` now registers Passport's routes of `laravel/mcp` (`/oauth/register`, the authorization and the token) and the metadata. It throws a `LogicException` that names the missing piece when Passport isn't installed, when `panneau.agents.mcp.guard` isn't a `passport` guard, when the users' model isn't Passport's, or when `passport.guard` isn't the guard of Panneau's users.

### Connect a client

Give the client the address of the server only, `https://example.com/panneau/mcp`. It finds the rest itself:

1. Its first call is a `401` whose `WWW-Authenticate` points to `/.well-known/oauth-protected-resource/panneau/mcp`.
2. It registers itself (`POST /oauth/register`) and opens the authorization in the browser.
3. A guest goes to the login of Panneau, and comes back to the consent page once logged in: in the `panneau` mode by the app's `next` parameter, in the `external` mode if the site's login redirects to the intended URL (`redirect()->intended()`).
4. The consent page, in the language of the interface, says whether the client will read, or read and change, the content. The user authorizes it.

With Claude Code:

```bash
claude mcp add --transport http panneau https://example.com/panneau/mcp
```

then `/mcp` in Claude Code to sign in.

### Check

```bash
curl https://example.com/.well-known/oauth-protected-resource/panneau/mcp
curl https://example.com/.well-known/oauth-authorization-server
```

Every address must be the public address of the site, with `https`, and `scopes_supported` is `["mcp:use","panneau:write"]`. Once the user has authorized a client, the account lists it under **Connections**, beside the personal tokens, and revoking it ends its tokens.

## Files

An agent sets a file field, or creates a media, with the `upload_file` tool ([decision 0032](decisions/0032-upload-file-tool.md)): it sends a file, and gets the value of the field, `{ "upload": "upl_…" }`, to give `save_item`. Three things to set up, with either way of signing in:

1. **The gate.** The tool is for a token that writes, whose user passes the `uploadPanneauFiles` gate, the one of [direct uploads](architecture.md#uploads). Without the gate, the tool answers a `403`, unless the project called `Panneau::allowWithoutPolicies()`.

   ```php
   Gate::define('uploadPanneauFiles', fn (User $user): bool => $user->isEditor());
   ```

2. **The table.** The files are kept as uploads, in the `panneau_uploads` table. With the server on, its migration is loaded: run `php artisan migrate`.
3. **The schedule.** An upload is temporary (`panneau.uploads.lifetime`, a day by default): the file field copies it when the item is saved. Remove the expired ones, used or not:

   ```php
   Schedule::command('panneau:prune-uploads')->hourly();
   ```

How the agent sends the file depends on the disk of the uploads (`panneau.uploads.disk`, else the default disk):

- **With direct uploads** (`panneau.uploads.enabled`, on an S3-compatible disk), the agent sends a small file in the call, in base64, and a larger one to a presigned URL, with one HTTP `PUT`, then completes the upload with the tool. `panneau.uploads.max_size` bounds both.
- **On a local disk,** or any disk without direct uploads, the agent sends the file in the call only. Nothing else to set up: the files go under `panneau.uploads.path` on the default disk, or on `panneau.uploads.disk` when it is set. Keep that disk private.

A file in the call is at most `panneau.agents.uploads.max_content_size` bytes, 5 MB by default:

```php
'agents' => [
    'uploads' => ['max_content_size' => 2 * 1024 * 1024],
],
```

A published `config/panneau.php` that lists `agents.tools` doesn't get the tool by itself: add `Panneau\Agents\Tools\UploadFile::class` to the list. To keep agents away from files, leave it out.

## Sanctum or Passport

The server uses one guard, so one package: `panneau.agents.mcp.guard`.

- **One `HasApiTokens` per model.** Sanctum's and Passport's traits have the same methods: a model uses one. A project that moves from Sanctum to Passport loses its Sanctum tokens: the server refuses them, and the account no longer lists them. Users make new ones.
- **A token of the other package is refused.** When the trait of the model isn't the package of the guard, `panneau:agent-token` refuses to make a token, and the account offers no button.
- **A project that already uses Passport for its API** shares its clients, its tables, its token lifetimes and its consent view with the agents. A consent view of the project is kept: it has to say what `panneau:write` means.
- **A project that uses Sanctum elsewhere** (an SPA, mobile apps) and wants OAuth for the agents can't keep both on one model today.

## Troubleshooting

### No Connections section in the account

The app shows it on the account page when the definition has an `agents` key. All of these are needed:

- the `panneau` auth mode (`panneau.auth.mode`): the `external` mode has no account page, so tokens come from `panneau:agent-token`;
- `panneau.agents.mcp.enabled` is `true`, in the config the app actually reads (`php artisan config:show panneau.agents`, and `php artisan config:clear` after a change in a cached config);
- `Panneau::mcpRoutes()` is called: `php artisan route:list --name=panneau.mcp`;
- the user passes `usePanneauAgents`, when the project defines it;
- `@panneau/app` 5.0.0-alpha.51 or later.

### No button to make a token

The definition has `"agents": {"mcp": {"tokens": false}}`. Run `php artisan panneau:agent-token` for the same user: it fails with the reason, the same ones as the account:

- the model has no `createToken()`: add the `HasApiTokens` of the package of the guard;
- its `HasApiTokens` is the other package's than `panneau.agents.mcp.guard`;
- with Passport, there is no personal access client for the users' provider: `php artisan passport:client --personal --provider=users`;
- with Passport, `panneau.agents.mcp.token_lifetime` is set: Passport doesn't take one.

### 401 Unauthorized

The guard of the server didn't find a user for the token.

- The client sends no `Authorization: Bearer …` header, or the web server drops it before PHP (some Apache and FastCGI setups: pass the header through).
- The token expired (`--days`, `panneau.agents.mcp.token_lifetime`, or `sanctum.expiration` on top), or was revoked in the account.
- The token belongs to the other package than `panneau.agents.mcp.guard`: a Sanctum token on a Passport guard, after a move to Passport.
- With Passport, a token of a client of another provider than the users'.

A session cookie is never enough: the server takes tokens only.

### 403 Forbidden

The user is found, and refused. The body is a problem that says why:

- `viewPanneau` refuses the user, or `usePanneauAgents` when it is defined;
- the token has neither `mcp:use` nor `panneau:write`: a token of the project with other abilities (`*` included) isn't one of the agents;
- `urn:panneau:problem:email-unverified`: the user verifies their address in Panneau first;
- `urn:panneau:problem:two-factor-required`: the user sets up two-factor authentication in Panneau first;
- the context problem: a value of the context in the address that the user can't use, or a resource that needs a value the address doesn't give.

### 419 on every call

`Panneau::mcpRoutes()` is in the `web` group, whose CSRF protection refuses the calls. Move it to `routes/panneau.php`.

### The login doesn't come back to the consent page

- `passport.guard` isn't the guard of Panneau's users: Passport never sees them logged in. `Panneau::mcpRoutes()` now refuses this setup with a `LogicException`; with an older version, check it.
- In the `panneau` mode, `panneau.middleware` must keep `web`: without the session, the login answers but doesn't last, and the next request is a guest again.
- In the `external` mode, the site's login must redirect to the intended URL (`redirect()->intended()`); Panneau stores the authorization there.
- In the `panneau` mode, `@panneau/app` 5.0.0-alpha.51 or later follows the `next` parameter. Earlier, the user opens the authorization again from the client.

### The metadata, or the address of the server, has the wrong URL

- Over HTTP, the OAuth metadata and the address in the account follow the request: `http` instead of `https`, or an internal host, means the proxy isn't trusted. Configure the [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies).
- `mcp.authorization_server` (the config of `laravel/mcp`), when it is set, replaces the address of the authorization server in the metadata.
- On the command line, `panneau:agent-token` prints the address from `APP_URL`: set it to the public address of the site.

### 429 Too Many Requests

The user went over `panneau.agents.rate_limit` calls a minute (60 by default). `Retry-After` says when to try again. `null` or `0` removes the limit.
