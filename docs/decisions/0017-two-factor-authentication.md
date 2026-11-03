# 0017: Two-factor authentication on Fortify's trait and actions, required by config, ability or role

- Status: proposed
- Date: 2026-11-03
- Issue: [#160](https://github.com/folkloreinc/laravel-panneau/issues/160), [folkloreinc/panneau-js#203](https://github.com/folkloreinc/panneau-js/issues/203)

## Context

[panneau-js decision 0049](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0049-two-factor-authentication.md) adds two-factor authentication to the `panneau` auth mode: a challenge after a password login, a setup from the app, a backend that requires it for some users, and a password confirmation for its changes.

[Decision 0005](0005-auth.md) uses Fortify as a library. Fortify already has what two-factor authentication needs: the `TwoFactorAuthenticatable` trait and its `two_factor_*` columns, the actions that enable, confirm and disable it and make new recovery codes, and a TOTP provider that refuses a code used twice. Its routes and its `Features` don't fit: they are the site's, and the contests platform, which uses Fortify for its own login on the same guard, may not turn two-factor on there.

The contests platform requires two-factor authentication for its internal and client accounts, which are roles of the roles module ([decision 0014](0014-roles-module.md)).

## Decision

- **`panneau.auth.two_factor`**, off by default, in `panneau` mode. The user model uses Fortify's trait, and the table has its columns: the same as a site that has two-factor authentication with Fortify, so both share them.
- **Fortify's actions and provider, Panneau's routes.** The login of `AuthController` validates the password, then answers `202` and keeps the pending login in the session for a user whose two-factor authentication is confirmed. `POST auth/two-factor/challenge` checks a code with Fortify's provider, or a recovery code, which is replaced. `/account/two-factor` runs Fortify's actions. A setup is on only once confirmed with a code, whatever `fortify.features` say: without the confirmation, a user who started a setup and never finished it couldn't log in.
- **Who must have it:** `panneau.auth.two_factor_required` is `false`, `true`, or a Gate ability that the users who must have it are allowed; and a role of the roles module with `'two_factor' => true` requires it for its users. The `panneau.auth` middleware refuses them with the `urn:panneau:problem:two-factor-required` problem until it is on, but on the routes of `/account/two-factor`, and serves them the page of the app without the definition. They can't turn it off (`409`).
- **Password confirmation** is Panneau's own, `panneau.auth.password_confirmed_at` in the session, set by a password login and by `POST auth/password/confirm`, for `panneau.auth.password_timeout` seconds (`auth.password_timeout` by default): the site's `password.confirm` and its key are another guard's, and Laravel's middleware answers with a redirect or a plain `423`. A request without a session (a token) has nothing to confirm.
- The TOTP provider is bound when Fortify's service provider isn't loaded; the URL and the QR code are built as Fortify's trait builds them.

## Consequences

- The contests platform declares its roles with `two_factor`, and its users set it up the first time they log in to the admin.
- A site whose Fortify has two-factor authentication shares the secret and the recovery codes with the admin, on the same users.
- A login with a token or an OAuth provider doesn't ask for the second factor.
