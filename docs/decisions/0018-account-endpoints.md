# 0018: The account is saved by Fortify's profile and password actions

- Status: proposed
- Date: 2026-11-03
- Issue: [#161](https://github.com/folkloreinc/laravel-panneau/issues/161), [folkloreinc/panneau-js#204](https://github.com/folkloreinc/panneau-js/issues/204)

## Context

[panneau-js decision 0050](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0050-account-page.md) adds an account page: `GET` and `PATCH /account` (the name and the email) and `PUT /account/password`. What a name is, whether an email must be unique and how a password is checked are the site's rules, which a site with Fortify already writes in `App\Actions\Fortify\UpdateUserProfileInformation` and `UpdateUserPassword`. Those stubs check the current password on the `web` guard, and send the site's own verification email to a new address.

## Decision

- **The routes exist in `panneau` mode,** with the `panneau.auth` middleware and without the context, and the definition has `auth.account`.
- **Fortify's contracts do the work:** `panneau.auth.update_profile_using` (`UpdatesUserProfileInformation`) and `panneau.auth.update_password_using` (`UpdatesUserPasswords`). A site can point them to its own Fortify actions.
- **Panneau's defaults** follow Fortify's stubs on any Eloquent user: a name, an email unique in the user's table, and `Password::defaults()` for the new password, checked against the current one with the hasher, so on any guard. With `panneau.auth.email_verification`, a new email of a `MustVerifyEmail` user is unverified and gets Panneau's link ([decision 0016](0016-password-reset-and-email-verification.md)); without it, the address keeps its verification.
- The protocol's `currentPassword` is Fortify's `current_password`, both ways: the action gets `current_password`, and its errors come back as `errors.currentPassword`. The action gets `password_confirmation` too, which the app checked. Changing the password confirms it for the changes that need a recent one ([decision 0017](0017-two-factor-authentication.md)).

## Consequences

- A site whose Fortify actions check the current password on `web`, with Panneau on another guard, keeps Panneau's default password action.
