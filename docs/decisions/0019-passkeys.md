# 0019: Passkeys on laravel/passkeys

- Status: proposed
- Date: 2026-11-03
- Issue: [#163](https://github.com/folkloreinc/laravel-panneau/issues/163), [folkloreinc/panneau-js#206](https://github.com/folkloreinc/panneau-js/issues/206)

## Context

[panneau-js decision 0051](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0051-passkeys.md) adds passkeys: a login, and the passkeys of the account, with options and credentials in the JSON form of the WebAuthn spec. The backend generates the options, keeps the challenge, and checks the attestations and the assertions: that is the part to get right, with a library.

The PHP libraries:

- **`web-auth/webauthn-lib`** (Spomky): the reference implementation of WebAuthn in PHP, complete and maintained, but low-level: options, serialization, storage of the credentials and their counters are left to the app.
- **`laravel/passkeys`**: Laravel's package on `web-auth/webauthn-lib`, with a `passkeys` table and model, a `PasskeyAuthenticatable` trait, actions for each step (options, registration, verification, deletion), events, and routes that an app can turn off. Fortify 1.4x requires it for its own passkeys, so every site with a recent Fortify already has it. It is a `0.x`.
- **`spatie/laravel-passkeys`**: also on `web-auth/webauthn-lib`, built around Livewire components; its actions are usable alone, but its table and trait differ from Fortify's.
- **`laragear/webauthn`** and **`lbuchs/webauthn`**: one is a full auth driver with its own guard and routes, the other a small standalone library without Laravel's storage.

## Decision

- **`laravel/passkeys`**, required directly (`^0.2`, the version Fortify requires). Panneau uses its actions, model, table and trait, behind a small internal service, and none of its routes: they would be on the site's guard and middleware, outside the protocol.
- A site that uses Fortify's passkeys shares the same table and the same passkeys with the admin, on the same users.
- **The user** implements `Laravel\Passkeys\Contracts\PasskeyUser` with its trait. The user of a login is read through the user provider of `panneau.auth.guard`, by the `user_id` of the passkey, so that a separate guard with its own model works.
- **The options of a ceremony** wait in the session for its second request, used once. A request without a session (a token) can't finish one.
- **The login** checks the `viewPanneau` gate, doesn't ask for the second factor, and counts as a password confirmation: the passkey verified the user. Adding and removing passkeys need a recent password.
- The relying party and the allowed origins are `laravel/passkeys`' config (`passkeys.relying_party_id`, `passkeys.allowed_origins`), which Fortify also sets.

## Consequences

- A `0.x` can break in a minor version: the service that wraps its actions keeps the change in one place.
- The cryptography is tested by `laravel/passkeys` and `web-auth/webauthn-lib`; Panneau's tests cover its routes, sessions and checks with a double of the service.
