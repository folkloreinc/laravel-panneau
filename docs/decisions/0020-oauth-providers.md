# 0020: OAuth providers with Socialite, linked to users by their verified email

- Status: proposed
- Date: 2026-11-03
- Issue: [#34](https://github.com/folkloreinc/laravel-panneau/issues/34), [folkloreinc/panneau-js#5](https://github.com/folkloreinc/panneau-js/issues/5)

## Context

[panneau-js decision 0052](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0052-oauth-providers.md) adds OAuth providers to the login page: the `url` of a provider starts its login with `next`, and a failure comes back to the app with `authError`. The backend chooses how a login with a provider becomes a login on its guard. [#34](https://github.com/folkloreinc/laravel-panneau/issues/34) proposed the shape: Socialite, allowed domains and Google's `hd` claim, no user created by default.

Socialite is the standard library for this in Laravel, with Google and the other common providers, and the site's own credentials in `config/services.php`. Some sites already use it for their members, with their own routes and callbacks.

## Decision

- **`panneau.auth.providers`**, by id: a label, allowed `domains`, `scopes`, the `with` parameters of the provider's request, and a Socialite `driver` when it isn't the id. `laravel/socialite` is suggested, and a provider without it is a `LogicException`.
- **Panneau's routes, the site's credentials:** `GET {path}/api/auth/providers/{provider}/redirect` and `…/callback`, for the configured ids only, outside the authentication and the context. Panneau sets the redirect URL of the driver to its callback, so a site's own Socialite callback for the same provider is left alone. The callback is documented in `openapi.json` as optional.
- **Who logs in:**
    - the email must be verified: Socialite gives no flag but Google's `email_verified`, which is required for `google`; other providers vouch for the addresses they give;
    - with `domains`, the email's domain must be in the list, and for Google its `hd` claim too, so that a personal account with an address of the domain doesn't pass. One domain also sets `hd` on the request, which shows only the accounts of that Workspace;
    - the user is the guard's user with this email, or the one that a `Panneau\Contracts\Auth\ResolvesProviderUsers` of `panneau.auth.provider_users` returns, which can link by another key or create users. Nobody is created otherwise;
    - the `viewPanneau` gate must allow them.
- **Then:** the login on the guard, without the second factor, counts as a password confirmation, and goes to `next` when it is a path under `{path}`, or to `{path}`. A failure goes to `{path}?authError=` with `provider`, `email`, `domain` or `access`.
- A redirection of these routes has no body, like the other answers of the API without content.
- A request without a session (a token) gets a stateless redirect: its callback can't log in on a session guard anyway.

## Consequences

- A site adds "Sign in with Google" with its credentials in `services.google`, the callback URL allowed in Google's console, and `panneau.auth.providers.google`.
- A site that wants accounts created at the first login writes a resolver.
