# 0016: Password reset and email verification send Panneau's own emails, to pages of the app

- Status: proposed
- Date: 2026-11-03
- Issue: [#158](https://github.com/folkloreinc/laravel-panneau/issues/158), [folkloreinc/panneau-js#196](https://github.com/folkloreinc/panneau-js/issues/196)

## Context

[panneau-js decision 0048](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0048-password-reset-and-email-verification.md) adds a password reset and an email verification to the `panneau` auth mode. The links of their emails open pages of the app; the protocol leaves to the backend what the tokens carry, which users get an email, and how the new password is checked and saved.

[Decision 0005](0005-auth.md) uses Fortify as a library, without its routes, views or global configuration, so that a site's own auth is left alone. Laravel's reset and verification notifications build their URL from a route of the site (`password.reset`, `verification.verify`) or from a callback set globally (`ResetPassword::createUrlUsing()`): a site that already uses them for its members would send Panneau's users to its own pages, or the other way around. The contests platform has its own Fortify on the `web` guard, and Panneau on the same guard.

## Decision

- **Opt-in, in `panneau` mode:** `panneau.auth.password_reset` and `panneau.auth.email_verification`, off by default. The definition has `auth.passwordReset` and `auth.emailVerification` when they are on, and the routes exist only then.
- **Panneau's own notifications.** `Panneau\Auth\Notifications\ResetPassword` and `VerifyEmail` extend Laravel's, with the URL of the page of the app (`{path}/auth/reset-password?token&email`, `{path}/auth/verify-email?token`), and their own static callbacks: the site's `toMailUsing()` and `createUrlUsing()` don't reach them, and theirs don't reach the site's.
- **Password reset on the broker,** `panneau.auth.passwords` (the default one otherwise). The link goes only to users that the `viewPanneau` gate allows in, and the answer is `204` in every case. The reset refuses users the gate refuses, like a wrong token. The new password is set by the Fortify action of `panneau.auth.reset_password_using` (`ResetsUserPasswords`): Panneau's default follows `Password::defaults()`, and a site can use its own Fortify action. The app checked the confirmation, so the action gets `password_confirmation` too. The reset doesn't log in.
- **Verification tokens are encrypted, not signed URLs.** A signed URL is tied to a route of the API, and the link opens the app. The token carries the id of the user, a hash of their address and an expiry (`auth.verification.expire`), encrypted with the key of the app: it proves the address without a session, and a new address makes it useless. The user is read through the user provider of `panneau.auth.guard`.
- **Who is refused:** with `email_verification`, the `panneau.auth` middleware refuses users that implement `MustVerifyEmail` without a verified address, with the `urn:panneau:problem:email-unverified` problem, on every route but those of the auth. They get the page of the app, without the definition. Users that don't implement `MustVerifyEmail` are never refused. Panneau doesn't send the first link: the site does when it creates the user, or the user asks for one from the page of the app.
- **Throttled** by the `panneau-auth` limiter, `panneau.auth.rate_limit` requests a minute per user or IP address.

## Consequences

- Adding the reset or the verification to an admin doesn't change the emails, routes or Fortify setup of the site.
- A site that customizes its reset email does it again for Panneau's, with `Panneau\Auth\Notifications\ResetPassword::toMailUsing()`.
- The conformance app runs in `panneau` mode with both features on, with the skeleton's users.
