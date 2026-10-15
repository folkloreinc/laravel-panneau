# AGENTS.md

`folklore/laravel-panneau` is the Laravel half of Panneau, Folklore's toolkit for admin panels and content management. The React half is [`panneau-js`](https://github.com/folkloreinc/panneau-js) (`@panneau/*`).

**Language.** This is a public repository. Everything written here is in **English**: code, comments, docs, commit messages, issues and their comments, pull requests and reviews. Never copy private details from the projects that use this package (product plans, infrastructure, internal docs, client names). Report security problems privately through a GitHub security advisory, never in a public issue, PR or commit message.

## Branches

| Branch | What it is |
|---|---|
| `v5.x` | Panneau 5, in development. Read [`docs/`](docs/README.md) first. |
| `v1.4` | Maintenance of 1.4 (Laravel 11+). Fixes and security only. |
| `v1.3` | Maintenance of 1.3. Fixes and security only. |

`master` and `develop` hold old code and are not maintained.

**Projects use the maintenance branches in production.** Some still require `v1.3.x-dev` or `v1.4.x-dev`, so every push to those branches reaches them on their next `composer update`. Every change goes through a pull request.

## Panneau 5

- **Plan:** tracked in [#35](https://github.com/folkloreinc/laravel-panneau/issues/35). The overall plan is [folkloreinc/panneau-js#6](https://github.com/folkloreinc/panneau-js/issues/6). Create a sub-issue when starting an item.
- **Architecture:** [`docs/architecture.md`](docs/architecture.md) and [`docs/decisions/`](docs/decisions/). The shared protocol and decisions live in [`panneau-js/docs`](https://github.com/folkloreinc/panneau-js/tree/v5.x/docs).
- **The protocol is the contract.** When the package needs something the protocol doesn't have, change the protocol first, in `panneau-js`.
- **Code status:** the v1 code is gone from `v5.x`. The package is being rebuilt during Phase 1, piece by piece (see #35).

## Rules for v5

1. **Secure by default.** Routes are opt-in, with middleware. Every operation is authorized by a Policy. Only validated data reaches a repository. Only declared fields are serialized.
2. **Resources are backed by repositories, not models.** Never assume Eloquent outside `EloquentRepository`. Items are any object.
3. **No static mutable configuration.** Settings come from config or the container.
4. **Public API.** Contracts, builders, resource methods meant to be overridden, config keys and events are public. Mark everything else `@internal` or make it `final`.

## Style

- PHP 8.3+ and Laravel 12+ (see [`docs/decisions/0006-supported-versions.md`](docs/decisions/0006-supported-versions.md)). Don't use language features newer than PHP 8.3.
- Typehint parameters, return values and properties.
- Format with [Laravel Pint](https://laravel.com/docs/pint) (`laravel` preset): run `composer format` before committing. CI runs `composer lint`.
- Static analysis runs with [Larastan](https://github.com/larastan/larastan): `composer analyse`. There is no baseline: fix errors, never ignore them.

## Tests

```bash
composer install
composer test
```

- PHPUnit with Orchestra Testbench. Fixtures live in `tests/fixture` (namespace `TestApp`).
- **Contract tests.** Every response under `/panneau/api` in the feature tests is checked against the protocol's `openapi.json` (copied in `tests/protocol`, refreshed with `tests/protocol/update.sh`). A test fails on an undocumented operation or status, or a body that doesn't match.
- **Conformance.** CI serves the fixture app (`testbench.yaml`) and runs `runConformance()` from `@panneau/protocol/conformance`, at the commit of `tests/protocol/VERSION`. To run it locally:

  ```bash
  vendor/bin/testbench workbench:build
  vendor/bin/testbench serve --port=8000 &
  tests/conformance/build.sh <panneau-js checkout> build/conformance.mjs
  node tests/conformance/run.mjs build/conformance.mjs http://127.0.0.1:8000/panneau/api
  ```
- **Every change comes with tests.** A bug fix adds a regression test that fails without the fix.
- Before pushing, run `composer lint`, `composer analyse` and `composer test`, and say in the PR what you ran.

## Workflow

- Branch from the target branch: `feature/<issue>-<slug>`, `fix/<issue>-<slug>`, `docs/…`, `ci/…`, `refactor/…`, `chore/…`.
- Open a pull request with `Closes #<issue>` or `Part of #<issue>` and the list of checks you ran.
- GitHub Actions (`.github/workflows/ci.yml`) runs Pint, Larastan and the tests (PHP 8.3–8.5 × Laravel 12–13) on every pull request. **Merge only when CI is green.** Never skip, disable or weaken a test to get there.
