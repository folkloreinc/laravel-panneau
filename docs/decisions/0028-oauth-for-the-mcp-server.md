# 0028: OAuth for the MCP server, with Passport, beside personal tokens

- Status: proposed
- Date: 2026-11-24
- Amends: [0022](0022-agents-on-laravel-mcp-and-laravel-ai.md), [0026](0026-agents-as-built.md)

## Context

[0026](0026-agents-as-built.md) shipped the MCP server with Sanctum's personal tokens, and left OAuth for later. Some clients can't take a pasted token: they discover the server's authorization, register themselves, and send the user to consent. A trial with Passport 13 and `laravel/mcp` 1.2 ran that flow end to end with no change to Panneau, and showed four gaps:

- **The metadata of `laravel/mcp` names one scope**, `mcp:use`: a client never learns of `panneau:write`.
- **Passport grants a scope to any client that asks for it**, even one registered with `mcp:use` only. The consent page is the only place where the user sees that a client will write.
- **Passport's consent view is the project's to write**, and a Passport access token lasts a year.
- **A client that asks only for `panneau:write`** got a token the server refused, since it has no `mcp:use`.

## Decision

The connections of [0022](0022-agents-on-laravel-mcp-and-laravel-ai.md) stand, with these changes:

- **OAuth is opt-in, beside personal tokens.** `panneau.agents.mcp.oauth.enabled` (false) with `laravel/passport` (`^13.0`, suggested, never required), a `passport` guard in `panneau.agents.mcp.guard`, Passport's keys and migrations, and its `HasApiTokens` on the user model. `Panneau::mcpRoutes()` then calls `Mcp::oauthRoutes()` (discovery and dynamic registration), with Passport's routes for the authorization and the tokens, and throws a `LogicException` without Passport. Personal tokens keep working: Passport's, or Sanctum's on another guard.
- **Panneau serves the metadata over the package's**, with the same URIs and route names (the exact and nested forms of the protected resource and of the authorization server), every key as the package gives it but `scopes_supported: ['mcp:use', 'panneau:write']`. The `WWW-Authenticate` of a `401` keeps pointing to the nested route.
- **Panneau registers both scopes with `Passport::tokensCan()`**, once the providers have booted, beside the scopes the project declared.
- **The consent page guards the write scope.** `panneau::oauth.authorize`, a Blade view without assets, names the Panneau, the client, the user, and what it asks in plain words: reading their content, or reading and changing it. Other scopes are listed by their Passport description. Panneau sets it with `Passport::authorizationView()` only when nothing is bound to Passport's `AuthorizationViewResponse` (Passport binds nothing of its own): a project that set its view keeps it, and a project that sets one later replaces Panneau's.
- **A token that can write can read.** `panneau.agents` accepts `panneau:write` without `mcp:use`, for Passport and Sanctum alike.
- **Lifetimes are the project's.** `oauth.access_token_lifetime` (minutes) and `oauth.refresh_token_lifetime` (days) go to `Passport::tokensExpireIn()` and `refreshTokensExpireIn()`; null leaves Passport's (a year). The config recommends an hour for access tokens.
- **A guest at the authorization goes to the login of the auth mode**, with the authorization as the intended URL of the session: the `external` mode's `login_url`, which can come back to it; the app's page in the `panneau` mode, whose login page doesn't follow an intended URL yet, so the user opens the authorization again.

## Consequences

- A project with Passport for its API shares its clients and its tables with the agents, and its consent view, which then has to say what `panneau:write` means.
- A client that asks for `panneau:write` gets it once the user consents: the page has to say it plainly, and does.
- Returning to the authorization after the app's login needs a change in panneau-js: its login answers in place, and doesn't follow an intended URL.
- The accounts' connections (`/account/connections`) are still to build.
