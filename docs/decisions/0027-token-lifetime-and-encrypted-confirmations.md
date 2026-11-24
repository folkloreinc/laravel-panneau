# 0027: Agent tokens can expire through Sanctum, and confirmations are encrypted by Laravel

- Status: proposed
- Date: 2026-11-24
- Amends: [0022](0022-agents-on-laravel-mcp-and-laravel-ai.md), [0026](0026-agents-as-built.md)

## Context

The agents lean on two pieces of security of their own:

- **`panneau:agent-token` makes tokens that never expire.** A personal token lives longer than a session, and a project had no way to give the tokens of its agents a lifetime, short of `sanctum.expiration`, which applies to every Sanctum token of the app.
- **The confirmation of `run_action` is signed by Panneau.** [0022](0022-agents-on-laravel-mcp-and-laravel-ai.md) signs it with `hash_hmac` over a base64url JSON payload, and [0026](0026-agents-as-built.md) binds it to the context. That is signing code Panneau has to get right (the comparison in constant time, the encoding, the key), and the agent can read the payload: the user's id, the connection, the context.

Laravel already has both: Sanctum stores an expiry per token and its guard refuses an expired one, and the encrypter authenticates and encrypts a string with the key of the app.

## Decision

- **A token made on the command line can expire.** `panneau:agent-token --days=N` passes `now() + N days` to `createToken()`, Sanctum's third argument. `panneau.agents.mcp.token_lifetime` is the default when `--days` isn't given: `null` by default, so tokens don't expire unless a project says so. `--days=0` means never, over the config. Anything but a whole number of days is refused, and no token is made. The command prints when the token expires.
- **Sanctum enforces it.** Panneau has no check of its own: the guard refuses an expired token with a `401`, and `sanctum.expiration` still applies on top.
- **Passport keeps its own lifetime.** Its `createToken()` takes no expiry: it is set for all personal tokens by `Passport::personalAccessTokensExpireIn()`. With such a model, a lifetime asked for is an error that says so; without one, the command works as before.
- **A confirmation is encrypted by Laravel's encrypter** (`StringEncrypter::encryptString()`), with the same claims and expiry as before: the user, the connection, the context, the resource, the action, a SHA-256 of the canonical arguments, and an expiry 5 minutes later. Verifying decrypts it, and a token that doesn't decrypt or isn't the expected JSON is refused like a changed one. The canonical hash of the arguments stays: it keeps the token short and makes the order of keys irrelevant. Panneau has no signing code for confirmations, and the agent can't read what the token holds.

## Consequences

- What a confirmation accepts and refuses doesn't change. Its token is longer: about 510 characters instead of about 320, which travels in a tool result and comes back as an argument.
- Rotating `APP_KEY` invalidates the pending confirmations, as it did the signed ones. Keys listed in `APP_PREVIOUS_KEYS` still decrypt them while they are valid.
- A project that wants its agents' tokens short-lived sets `panneau.agents.mcp.token_lifetime`, without shortening its other Sanctum tokens. Tokens made before keep no expiry.
- `Panneau\Auth\EmailVerification` keeps its own signature for now.
