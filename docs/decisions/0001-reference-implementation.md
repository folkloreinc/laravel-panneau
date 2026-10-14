# 0001: Reference implementation of the protocol

- Status: accepted
- Date: 2026-10-14

## Context

Panneau 5 defines a written protocol between frontend and backend ([shared decision 0002](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0002-protocol-as-contract.md)). Until now, the HTTP API of `laravel-panneau` was implicit, and it no longer matched what `panneau-js` calls.

## Decision

- **`laravel-panneau` 5 implements the whole protocol:** definition, resources, actions, media, auth, search, dashboard and preview.
- **Contract tests.** Its feature tests validate every response against the OpenAPI document of `@panneau/protocol`, at the version required by the package.
- **Spec first.** When the package needs something the protocol doesn't have, the protocol changes first, in `panneau-js`.

## Consequences

- The package and `@panneau/protocol` are released with matching majors.
- Laravel conventions (Laravel's 422 format, `_method`, `JsonResource` envelopes) are converted at the edge of Panneau routes, and only there.
