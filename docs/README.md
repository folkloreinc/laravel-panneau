# laravel-panneau 5

`folklore/laravel-panneau` is the Laravel half of Panneau, Folklore's toolkit for admin panels and content management. Version 5 ships together with `@panneau/*` 5 under the name "Panneau 5".

Most of the architecture is shared with the frontend. It is documented in [`panneau-js/docs`](https://github.com/folkloreinc/panneau-js/tree/v5.x/docs):

- [overview and goals](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/README.md);
- [architecture](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/architecture.md);
- [protocol](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/protocol.md): the definition and HTTP API this package implements;
- [roadmap](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/roadmap.md);
- [migration](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/migration.md);
- [shared decisions](https://github.com/folkloreinc/panneau-js/tree/v5.x/docs/decisions).

This folder covers the Laravel side.

| Document | Content |
|---|---|
| [current-state.md](current-state.md) | Audit of `laravel-panneau` v1.3 and v1.4 |
| [architecture.md](architecture.md) | How the package implements the protocol: resources, fields, HTTP layer, auth, media, install |
| [migration.md](migration.md) | Laravel steps to migrate a project from v1 |
| [decisions/](decisions/) | Laravel-specific decision records |

Tracking issue: [#35](https://github.com/folkloreinc/laravel-panneau/issues/35). Overall plan: [folkloreinc/panneau-js#6](https://github.com/folkloreinc/panneau-js/issues/6).
