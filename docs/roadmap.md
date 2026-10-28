# Roadmap of 5.0 (Laravel)

The Laravel side of Panneau 5. The overall plan, the protocol and the shared decisions are in [`panneau-js/docs/roadmap.md`](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/roadmap.md) and [folkloreinc/panneau-js#6](https://github.com/folkloreinc/panneau-js/issues/6).

5.0 is built on the `v5.x` branch in slices named by theme. Pre-releases (`v5.0.0-alpha.N`, then beta and RC) are tagged when a part is ready, each with the `@panneau/*` version it goes with, and each item says the release that shipped it; what each release contains is in the [CHANGELOG](../CHANGELOG.md). The pilots pull the slices: Niche for the pieces (fields, forms, lists, data), the contests platform for the full admin of phase 2 (auth, roles, actions, media, the `laravel-folklore` plugin). 1.3 and 1.4 stay maintained on `v1.3` and `v1.4` until their projects migrate.

Index: [#35](https://github.com/folkloreinc/laravel-panneau/issues/35). Each item becomes a sub-issue of it when its work starts.

## Rules

- **The protocol is the contract.** A change the package needs in the definition or the API is made in `panneau-js`'s protocol first, and the contract tests and the conformance kit check it here.
- **Each pre-release is usable end to end** for what it contains, with docs.
- Until the release candidate, an alpha can break the previous one: the CHANGELOG says how to upgrade.
- **Beta:** `5.0.0-beta.1` is tagged when the protocol and the public API are frozen. From then on, only additions, and nothing breaking without a deprecation. The remaining features of phase 2 can still land in the betas.

## Foundations and core (done)

- [x] Tooling on `v5.x`: GitHub Actions CI (PHP 8.3 to 8.5 × Laravel 12 and 13), Pint, Larastan, PHPUnit with Testbench, `AGENTS.md`; PHP 8.3+ and Laravel 12+ ([decisions/0006](decisions/0006-supported-versions.md), #45) — before the alphas
- [x] `ResourceRepository`, `EloquentRepository`, `ResourceQuery` (#61) — 5.0.0-alpha.1
- [x] Resource, field, layout, list and action builders that emit the definition; portable validation rules (#63) — 5.0.0-alpha.1
- [x] The HTTP API of the protocol: definition, resources, actions with selections, `ETag`/`If-Match`, problem+json, Policies, abilities on items (#65) — 5.0.0-alpha.1
- [x] Contract tests against `openapi.json`, and the conformance kit in CI (#67) — 5.0.0-alpha.1
- [x] `panneau:install` and the page of the app (#69, #71) — 5.0.0-alpha.1

## What the pieces' pilot needed (done)

- [x] Theme in the definition (#74) — 5.0.0-alpha.2
- [x] Date-range filters read as bounds (#73) — 5.0.0-alpha.2
- [x] Dry runs of actions (#77) — 5.0.0-alpha.3
- [x] Direct uploads, owned by Panneau ([decisions/0007](decisions/0007-direct-uploads.md), #81) and the `File` field ([decisions/0008](decisions/0008-field-preparation.md)) — 5.0.0-alpha.3
- [x] Media libraries are resources: `MediaResource`, `MediathequeResource`, the `Media` field ([decisions/0009](decisions/0009-media-libraries.md)) — 5.0.0-alpha.3
- [x] Replacing a media's file on the mediatheque (#93) — 5.0.0-alpha.4
- [x] Parts of the API, and routes under a prefix with its own parameter (#87, #88) — 5.0.0-alpha.4
- [x] Global search, through the repositories or Scout ([decisions/0010](decisions/0010-global-search.md), #96) — 5.0.0-alpha.4
- [x] Slugs that check their value ([decisions/0011](decisions/0011-slug-checks.md), #99) — 5.0.0-alpha.4
- [x] More field types: `Tel`, `Range`, `Duration`, `Json`, `Tags`, `Embed`, `Location` ([decisions/0012](decisions/0012-embeds-and-more-field-types.md), #100) — 5.0.0-alpha.5

## Media (next)

- [x] The `Image` field: the crop rule, the base of a transform, and the media's own transform ([#98](https://github.com/folkloreinc/laravel-panneau/issues/98)), with `laravel-image` 2 and the mediatheque (folkloreinc/laravel-image#88, folkloreinc/laravel-mediatheque#107) — 5.0.0-alpha.5
- [x] The `Media` field on several libraries, and references resolved with their library ([#95](https://github.com/folkloreinc/laravel-panneau/issues/95)) — 5.0.0-alpha.5

## App and content (phase 2, with the contests platform)

In the order the contests platform moves its admin ([its tranches](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/roadmap.md)):

- [ ] Pages and menus in the definition, for the app shell ([#33](https://github.com/folkloreinc/laravel-panneau/issues/33))
- [ ] Auth: password, reset, 2FA, passkeys, email verification (Fortify), OAuth providers (Socialite, [#34](https://github.com/folkloreinc/laravel-panneau/issues/34)), with a separate guard or the site's existing auth ([decisions/0005](decisions/0005-auth.md))
- [ ] Optional roles module, and permissions in the UI
- [ ] Actions: queued runs with progress; built-in duplicate, restore, export and import
- [ ] Inline editing in lists: what a column needs to edit its field in place; the update already takes a single field with `If-Match`
- [ ] The `laravel-folklore` plugin, built in `laravel-folklore` 2 ([its decision 0018](https://github.com/folkloreinc/laravel-folklore/blob/v2.x/docs/decisions/0018-panneau-plugin-php-only.md)): a repository adapter on its typed query, and the `Page`, `Pages`, `Blocks` and `PageSlug` fields in PHP on the built-in types (`relation`, `repeater`, `slug`). Here: what the adapter needs, and `ChecksSlugs` (done)
- [ ] Migration guide, `panneau:install --upgrade` and Rector rules, validated on the contests platform

## 5.0.0

- [ ] Released together with `@panneau/*` 5.0.0

## 5.x (minor releases)

- [ ] Dashboard widgets
- [ ] Preview, drafts and revisions
- [ ] Realtime collaboration support (signed tokens for a Yjs server)
- [ ] AI agents, with the user's abilities and the policies: the resources and actions of the definition as tools, built once (see the panneau-js roadmap)
    - an MCP server on `laravel/mcp`, for outside AI clients signed in as the user with OAuth 2.1 (Passport) or a Sanctum token;
    - an assistant inside Panneau on the Laravel AI SDK (`laravel/ai`), on the same tools, with streamed answers

## Maintenance of 1.x

`v1.3` and `v1.4` stay maintained (fixes and security) until their projects migrate. Older branches that projects still require (`v0`, `v1`, `v1.2`) get no fixes.
