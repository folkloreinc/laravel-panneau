# Changelog

All notable changes to `folklore/laravel-panneau` 5.x are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/). The releases of 1.3 and 1.4 are on [GitHub](https://github.com/folkloreinc/laravel-panneau/releases).

5.0 is released as pre-releases first (`5.0.0-alpha.N`, then beta and RC), each with the `@panneau/*` version it goes with. Until the release candidate, a pre-release can break the previous one: each one says how to upgrade from the previous one. The GitHub release of each version has the details and examples.

## [Unreleased]

### Added

- `php artisan panneau:install --upgrade` migrates a 1.3 or 1.4 project: it converts `config/panneau.php`, writes the service provider, routes file and JS entry (Vite next to the site's bundler), moves a 1.x override of the page aside, keeps what it changes as `{file}.1x.bak` (never overwriting a backup), and lists what to move by hand and the files the package now provides. `--upgrade --check` reports what the project has and what each part becomes, without writing: resources, types, fields, controllers, routes, resolvers, request macros, build and deployment files. It reads the project without running it ([decisions/0013](docs/decisions/0013-upgrade-from-1x.md), [migration guide](docs/migration.md), #114).

- Rector rules for 1.x resources and fields, in `rector.php`: static properties to methods, `index()` to `list()`, field classes and builder methods to 5.x, and `// TODO(panneau-5)` comments where they can't convert, which `--check` lists ([migration guide](docs/migration.md#5-run-the-rector-rules), #115).

### Changed

- `folklore/laravel-panneau` requires `nikic/php-parser` ^5.0, for the upgrade (#114).

## [5.0.0-alpha.5] - 2026-10-27

Goes with `@panneau/*` `5.0.0-alpha.6`.

### Added

- Seven field types: `Tel`, `Range`, `Duration`, `Json`, `Tags`, `Embed` (`GET /embeds`, on an allowlist of oEmbed providers, resolved again at save) and `Location` ([decisions/0012](docs/decisions/0012-embeds-and-more-field-types.md), #100, #101). They go with `@panneau/*` `5.0.0-alpha.6`.
- A `media` field can allow several libraries: `resource([Images::class, Documents::class])`, or every library of the definition without `resource()`. Its values carry the `resource` of each media, which requests need when the field allows several libraries, and references give it always (panneau-js decision 0027, #95).
- The `Image` field, a `media` field for images: `ratios()`, `rotate()`, `focalPoint()`, `transformBase()`, and `accept` `image/*` by default (panneau-js decision 0031, #98).
- The `crop` portable rule on `media` and `image` fields, `crop(ratios: [...], required: true)`, checked on the transform of each media (#98).
- `MediaInfo.error` on the mediatheque: why a failed media failed (`{ code, message }`), from its latest failed run or the rendition that failed (#108).
- Transforms keep `base` and `baseTransform`, for a transform made over the media's own, and the keys of editor tools (#98).
- The media's own transform in `MediaInfo.transform`, and so in references: `MediaResource::mediaTransform()`, from the library's `transform` field or the mediatheque's `transform` (#98).

### Changed

- `MediaTransform::normalize()` keeps the keys it doesn't know instead of dropping them (#98).
- References of `media` values have a `resource`, the media's library. A `media` field without `resource()` allows every library, instead of finding none when the definition has several. `ValidationContext` has a `libraries` closure, given by the controllers (#95).

### Breaking changes

- `MediathequeResource` needs `laravel-mediatheque` 2.0.0-alpha.8 or later, which has the media's `transform` (#98).
- A `media` field that allows several libraries stores `{ id, resource, transform }`: a value saved without `resource` before the field allowed several libraries isn't resolved, and needs migrating (#95).

## [5.0.0-alpha.4] - 2026-10-23

Goes with `@panneau/*` `5.0.0-alpha.5`.

### Added

- Global search: `GET {panneau.path}/api/search`, through the repositories, or Laravel Scout for searchable models; `q`, `limit` and `resources`; the `panneau-search` rate limiter; `panneau.search.enabled` ([decisions/0010](docs/decisions/0010-global-search.md), #96, #97).
- Slugs that check their value: `Slug::unique()`, `url()`, `disabledWhen()`, `POST resources/{resource}/fields/{field}/check`, and `ChecksSlugs` for repositories that aren't Eloquent ([decisions/0011](docs/decisions/0011-slug-checks.md), #99).
- Replacing the file of a media on the mediatheque with `Mediatheque::replaceOriginal()`; `replace()` lets a library allow type changes. Needs `laravel-mediatheque` 2.0.0-alpha.5 or later (#93, #94).
- Parts of the API: `Panneau::uploadRoutes()` and `Panneau::resourceRoutes($resources, only: [...])`, without the app page (#87).
- Routes under a prefix with its own parameter: the controllers read their route parameters by name (#88, #90).

### Changed

- A media library explains why it refuses the media of `Mediatheque::fake()` (#89, #91).
- The search route is on by default.

## [5.0.0-alpha.3] - 2026-10-22

Goes with `@panneau/*` `5.0.0-alpha.4`.

### Added

- Direct uploads to an S3-compatible disk, signed by Panneau (Uppy 6): `POST uploads/sign` and `POST uploads/{upload}/complete`, off by default, behind the `uploadPanneauFiles` gate; the `panneau_uploads` table and `panneau:prune-uploads`; `ActionValues::upload()` ([decisions/0007](docs/decisions/0007-direct-uploads.md), #82).
- The `File` field, and `Field::prepare()` with `SaveContext` ([decisions/0008](docs/decisions/0008-field-preparation.md), #83).
- Media libraries are resources: `MediaResource` on an Eloquent model, or `MediathequeResource` on `laravel-mediatheque` 2; the `Media` field, with a per-use `transform` ([decisions/0009](docs/decisions/0009-media-libraries.md), #86).
- Dry runs of actions (#77, #78).

### Breaking changes

- The protocol has no `/media` endpoints any more: media are items of a `media` resource.
- `Media` fields name a library (optional with a single one) and store `{ id, transform }`, or ids with `->transforms(false)`.
- A list in a resource's `scope()` is a `whereIn` constraint, and isn't set on created items.

## [5.0.0-alpha.2] - 2026-10-20

Goes with `@panneau/*` `5.0.0-alpha.2`.

### Added

- The theme of `config('panneau.theme')` in the definition (#74, #75).
- `DateRangeFilter` reads the bounds of a range (`filter[published_at][from]`, `[to]`) (#73, #76).

### Breaking changes

- Repositories other than `EloquentRepository` receive a date-range filter as `['from' => …, 'to' => …]`, and `ResourceQuery` keeps the `from` and `to` keys of a filter value.

## [5.0.0-alpha.1] - 2026-10-16

Goes with `@panneau/*` `5.0.0-alpha.1`. A rewrite: the reference implementation of the Panneau protocol. PHP 8.3+, Laravel 12 or 13.

### Added

- The `ResourceRepository` contract, `EloquentRepository`, adapters registered by contract, and `ResourceQuery` (#61, #62).
- Resource, field, layout, list and action builders that produce the protocol's definition (#63, #64).
- The HTTP API of the protocol: opt-in routes with middleware, definition, list, show, create, update, delete, actions, `ETag`/`If-Match`, `application/problem+json`, required Policies, validated data only (#65, #66).
- Contract tests against the protocol's OpenAPI document, and its conformance kit in CI (#67, #68).
- `php artisan panneau:install`, and the admin page with its JS entry (#69, #70, #71, #72).

[Unreleased]: https://github.com/folkloreinc/laravel-panneau/compare/v5.0.0-alpha.5...v5.x
[5.0.0-alpha.5]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.5
[5.0.0-alpha.4]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.4
[5.0.0-alpha.3]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.3
[5.0.0-alpha.2]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.2
[5.0.0-alpha.1]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.1
