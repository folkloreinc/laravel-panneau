# 0002: Secure by default

- Status: accepted
- Date: 2026-10-14

## Context

In v1:

- routes are registered automatically, without `web` or `auth` middleware;
- index, show, destroy and clone check no authorization;
- the data saved is the whole request minus `_token`, not the validated data.

Projects that forget a middleware or a policy expose their admin API. Niche keeps the v1 routes disabled for this reason.

## Decision

- **Routes are opt-in.** Nothing is registered until the project calls `Panneau::routes()`.
- **Middleware.** Panneau routes always get `config('panneau.middleware')`, `['web', 'panneau.auth']` by default. Removing authentication requires an explicit setting.
- **Policies are required.** Every resource operation is authorized through the Policy of the item (an Entity contract or a model). A resource without a policy is denied, unless the project calls `Panneau::allowWithoutPolicies()` (meant for local development).
- **Only validated data is saved,** through the fields. Undeclared attributes are never filled.
- **Only declared fields are serialized** by default. Hidden attributes don't leak.
- **Filters, sorts and search only accept what the resource declares.**
- **Every operation goes through the resource's `scope()`,** so tenant or ownership scoping applies to show, update, delete and actions, not only to lists.
- **Upload validation** (MIME type, size, extension) comes from the field rules. SVG files are sanitized or refused.
- **Throttling.** Auth endpoints are rate-limited.

## Consequences

- A fresh install exposes nothing until a resource has a policy and the routes are mounted.
- `panneau:install` generates the policies and the gate, so this stays easy.
