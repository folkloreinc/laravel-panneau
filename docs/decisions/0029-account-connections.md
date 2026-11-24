# 0029: The connections of the account, and the agents key of the definition

- Status: proposed
- Date: 2026-11-24
- Amends: [0022](0022-agents-on-laravel-mcp-and-laravel-ai.md), [0026](0026-agents-as-built.md), [0028](0028-oauth-for-the-mcp-server.md)

## Context

The MCP server reads and writes behind a personal token ([0026](0026-agents-as-built.md)) or OAuth ([0028](0028-oauth-for-the-mcp-server.md)). A user can only get a token from the command line, can't see what is connected to their account, and the app can't tell that the server is on: the three are left open by 0026 and 0028.

[0022](0022-agents-on-laravel-mcp-and-laravel-ai.md) gave `Panneau\Agents\Connections` the job of listing and revoking the OAuth clients and the tokens of a user, through whichever package the guard uses. The protocol has the `agents` key of the definition, `{ mcp?: { url }, assistant? }`, but no schema yet for the endpoints of the account: this decision fixes their shape, for the protocol to follow.

## Decision

### What a connection is

- **A connection is what lets an agent in as the user**, through the package of the guard of `panneau.agents.mcp.guard`, read with its own models:
    - **Sanctum:** each personal access token of the user whose abilities name `mcp:use` or `panneau:write`, and that hasn't expired (its `expires_at`, or `sanctum.expiration` after its creation). Revoking it deletes it.
    - **Passport:** each OAuth client for which the user holds an access token with one of those scopes that isn't revoked, and that still works: not expired, or with a refresh token that isn't revoked or expired. **One connection per client**, not per token: a client that refreshed its token ten times is one line. Revoking it revokes all the user's access tokens, refresh tokens and authorization codes for that client, not the client itself, which other users may have authorized. A personal access token of Passport (its client has the `personal_access` grant) with one of those scopes is a connection of its own.
- **Tokens with other abilities aren't connections, `*` included.** They are the project's (an API, a mobile app): the account page of Panneau doesn't list or revoke them, even though `*` passes `tokenCan()`. A project that gives `*` tokens to its users already lets them in through whatever made those tokens.
- **The shape:**

  ```json
  {
    "id": "token:12",
    "type": "token",
    "name": "Claude",
    "write": true,
    "createdAt": "2026-11-24T10:00:00+00:00",
    "lastUsedAt": "2026-11-24T11:30:00+00:00",
    "expiresAt": null
  }
  ```

  `id` is opaque, `token:{id}` or `client:{id}`: the app sends it back and never parses it. `type` is `token` or `client`. `write` says whether its agents can use the tools that write (`panneau:write`; for a client, any of its working tokens). Dates are ISO 8601, as the passkeys of the account. `lastUsedAt` is Sanctum's `last_used_at`, and always null with Passport, which doesn't record it. `expiresAt` is null for never; for a client, the latest end of its working tokens, refresh tokens included. The list is the newest first.

### The endpoints

In the `panneau` auth mode, beside the other endpoints of the account, without the context, and only when `panneau.agents.mcp.enabled` is on. The user passes `usePanneauAgents` when the project defines it, else a `403` problem.

- **`GET /account/connections`** → `200` `{ "data": [Connection] }`.
- **`POST /account/connections/tokens`** with `{ "name": string, "write"?: boolean }` → `201` `{ "token": string, "connection": Connection }`. The plain text of the token is answered once. It is made by the same code as `panneau:agent-token`, `Connections::createToken()`: `mcp:use`, and `panneau:write` with `write`, expiring after `panneau.agents.mcp.token_lifetime` days through Sanctum. With Passport, it is a personal access token, which needs the project's personal access client, and Passport's `createToken()` takes no expiry ([0027](0027-token-lifetime-and-encrypted-confirmations.md)). A missing `name` or a `write` that isn't a boolean is a `422`. **When no token can be made** for the user (`Connections::canCreateTokens()`: the model has no `createToken()`; with Passport, there is no personal access client for the user's provider, as Passport's `ClientRepository::personalAccessClient()` resolves it, or a lifetime is in the config), it is a `409` problem (`about:blank`, with a `detail`), as the account answers a user who can't have passkeys, rather than the `500` of the exception. The command keeps refusing with the same reasons.
- **`DELETE /account/connections/{id}`** → `204`, and the token stops working at once. An id that isn't a connection of the user, including a token of theirs with other abilities, is a `404` problem.
- **Creating and revoking need a recent password**, the `423` problem, as the passkeys and the two-factor authentication of the account do. Listing doesn't.

### The definition

- **`agents.mcp.tokens`** is always there with `url`: true when the account can make a personal token for the user (`Connections::canCreateTokens()`, one query with Passport, none with Sanctum), so that the account page doesn't offer one that would be refused. The protocol's `agents.mcp` takes other keys; its schema follows in panneau-js.
- **`agents.mcp.url`** is the absolute URL of the MCP server, without the prefix of a context, when `panneau.agents.mcp.enabled` is on, `Panneau::mcpRoutes()` registered the `panneau.mcp` route, and the user passes `usePanneauAgents` when the project defines it. There is no `agents` key otherwise, so that definitions stay as they were. The `ETag` of the definition is computed over its content, so it follows.
- **The connections endpoints don't need the key**, and the key doesn't need the `panneau` auth mode: in the `external` mode, the app knows the URL of the server, and the project gives its own account page.

### Set aside

- **`assistant`** in the `agents` key: it comes with the assistant.
- **Creating the personal access client of Passport**: `panneau:install --agents` will; until then, `agents.mcp.tokens` is false.
- **Editing a connection** (its name, or adding `panneau:write`): a user revokes it and makes another, which is what the consent of OAuth asks anyway.
- **The ids of `AgentCaller`** stay the raw key of the token, without the `token:` prefix: they are traces, not ids of the account.

## Consequences

- The account page of panneau-js can list, make and revoke the connections of a user with three operations, show the server's URL from the definition, and offer a token only when `agents.mcp.tokens` is true. The protocol documents them from this decision; until then, the tests of this package check them against `tests/protocol/pending.openapi.json`.
- A project with its own API tokens keeps them out of the account page by giving them other abilities than `mcp:use` and `panneau:write`.
- With Passport, a user who revokes a client is asked to consent again the next time the client connects.
- With Passport, `lastUsedAt` is always null, and a client's `expiresAt` moves forward each time it refreshes its token.
