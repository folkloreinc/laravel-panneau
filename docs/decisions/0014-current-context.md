# 0014: The context is checked param by param, through the repositories, on every request

- Status: proposed
- Date: 2026-11-03
- Issue: [#147](https://github.com/folkloreinc/laravel-panneau/issues/147), [folkloreinc/panneau-js#182](https://github.com/folkloreinc/panneau-js/issues/182)

## Context

[panneau-js decision 0045](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0045-current-context.md) adds a current context: params declared in the definition, whose values the app sends with every request in `Panneau-Context`. The backend checks each value for the user, gives the values to resources, and builds the definition for them. It leaves to the backend how values are checked, where they come from in a resource, and what happens without a value.

The contests platform, its first user, picks a contest, then a client of that contest: the choices of a param depend on the values before it, and most choices are items of a resource that the user may or may not see.

## Decision

- **Declared in a service provider**, with `Panneau::context()`: `ContextParam` builders, or a closure called through the container with the user. Not in `config/panneau.php`: choices are resources and closures.
- **A param of a resource is checked by its repository.** The value is found with `find()` within the resource's `scope()`, and the user must be allowed to `view` it by its policy. Its choices are the first page of the repository's `query()`, with the search of its list (or its title) and its scope, filtered by `view`. `viewAny` isn't asked: a user can pick their contest without listing the contests. Other params take inline `options()`, or a `search()` and a `find()`: Panneau never checks a value against what the client sent.
- **Param by param, in order.** Each param is checked with the values before it: its closures get them as `$context`, and while its resource is queried, the current `Context` holds only them, so that the resource's `scope()` reads what the param depends on. A refused value stops there, with the `403` problem of that param.
- **One middleware, on every route of the API** (`ResolveContext`, alias `panneau.context`), after the authentication, also in the groups of `resourceRoutes()` and `uploadRoutes()`, but not on login and logout. It fills `Panneau\Context\Context`, bound once per request, which resources read in `scope()`. Without a declared context, it does nothing, and responses don't vary.
- **Defaults are values like the others.** A param without a value gets its `default()`, checked the same way. A default the user can't use is left out rather than refused: the client didn't send it, and a `403` would bring it back to the same request.
- **No value is no value.** `Context::get()` is null without one; a resource that needs a value calls `getOrFail()`, which answers the `403` context problem. Panneau doesn't refuse the routes of a resource on its own, since only the resource knows whether it needs the param.
- **The page of the app** reads the prefix of its path (`{name}/{value}/…`, URL-encoded, as the request has it), and builds the inlined definition for those values. A value the user can't use leaves the definition out: the app fetches it and handles the `403`.

## Consequences

- Every request with a context checks each value: one `find()` and one policy check per param of a resource. A product with many params or slow repositories caches in its repository, not in Panneau.
- A resource can't be scoped by a value the user can't see: the scope only gets checked values.
- The choices of a param of a resource are filtered after the query: a page can have fewer than 20 choices when the policy refuses some of them. A scope that keeps to what the user can see avoids it.
- `GET /search` and `GET /embeds` get the same `403` for a refused value, which the protocol's OpenAPI document doesn't list for them yet.
