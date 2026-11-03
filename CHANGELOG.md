# Changelog

All notable changes to `folklore/laravel-panneau` 5.x are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/). The releases of 1.3 and 1.4 are on [GitHub](https://github.com/folkloreinc/laravel-panneau/releases).

5.0 is released as pre-releases first (`5.0.0-alpha.N`, then beta and RC), each with the `@panneau/*` version it goes with. Until the release candidate, a pre-release can break the previous one: each one says how to upgrade from the previous one. The GitHub release of each version has the details and examples.

## [Unreleased]

### Breaking changes

- `Tel` values are phone numbers in E.164 (`+15145550199`): the type implies the protocol's new `tel` rule, and the server refuses any other value. Convert the numbers kept as typed, or their forms refuse them until they are retyped (folkloreinc/panneau-js decision 0047).

### Added

- `php artisan panneau:convert-phones` turns the phone numbers that a project kept as typed into E.164, in the columns and JSON keys of `panneau.phones.columns`, without a library of numbering plans; `Panneau\Fields\PhoneNumber` converts and formats them, and `panneau.phones.country` is the country of the `Tel` fields without `country()` (folkloreinc/panneau-js decision 0047).
- `Country` and `Region` fields, the `country` and `region` types: a country is an ISO 3166-1 alpha-2 code (`Country::CODES`, with `XK`), limited with `countries()` and ordered with `preferred()`; a region is a string, whose country comes from the field that `from()` names or from `country()`. `Tel::country()` sets the country of a new number. Lists show `country` and `region` fields without columns (folkloreinc/panneau-js decision 0047).

## [5.0.0-alpha.8] - 2026-11-02

Goes with `@panneau/*` `5.0.0-alpha.10`, which it needs: earlier admins ignore the rules limited to a mode and would require a `Password` on every update.

The built-in item actions of panneau-js decision 0040 (show, edit, delete and, for resources that opt in, duplicate, shown by each item's abilities), `Resource::open()`, rules limited to create or update with the `Password` field, and fixes to the upgrade from 1.3 and 1.4 found on a second project.

### Breaking changes

- Every resource now has the built-in `delete`, and lists show the built-in item actions by each item's abilities, from the resource's policy: a policy that allows `delete` now shows a Delete button where alpha.7 showed none. Restrict it in the policy, or hide it with `itemActions()`.
- Requires `@panneau/*` `5.0.0-alpha.10` or later (see above).

Needs `@panneau/*` 5.0.0-alpha.10 or later, for the rules limited to a mode: an older admin applies them in both modes, and requires the password of a user on update.

### Added

- Portable rules limited to a mode of the form: `required()`, `min()`, `max()`, `pattern()`, `in()`, `before()` and `after()` take a `mode` (`required(mode: FormMode::Create)`), sent as `{ "rule": "required", "mode": "create" }`, and the server applies them only in that mode; in the other, the field accepts no value. `isRequired()` takes a mode (folkloreinc/panneau-js#165, #140).
- In the `panneau` auth mode, guests get the page of the app, without the definition, instead of a redirect: the app shows its login page when the API answers `401` (panneau-js decision 0037). `POST /auth/login` logs in with an email and a password on the guard of `panneau.auth.guard` (a session guard), throttled, and only for users the `viewPanneau` gate allows in; `POST /auth/logout` logs out. Password reset, two-factor and OAuth providers come later (#132).
- The boot config has `auth`, the `auth` of the definition, so the login page knows which logins to offer before the definition is loaded (#132).
- `panneau.auth.logout_url`, sent as `auth.logoutUrl` in the `external` mode: where the browser goes to log out, the site's own logout, which has to accept a `GET`. The app shows a "Log out" button only when it is set (#132).
- The `file` field of a `MediathequeResource` gets the mediatheque's size limit as its `maxSize`: the largest `max_size` of the library's types (or `ingest.max_size`), none when one has no limit. An explicit `maxSize()` in `file()` wins. `MediaResource::maxFileSize()` gives the default of other libraries, none by default (#136).
- A `Password` field, for users resources: a `text` field with `settings.secret` and `{ "rule": "required", "mode": "create" }` in the definition, so the admin marks it required on create only. It is never sent, required to create the item only (`required()` included; `required(mode: FormMode::Update)` on update too), optional to update it (an empty value keeps the current password), checked with `Password::defaults()`, and hashed once, unless it is already a hash that the app's hasher accepts (`Hash::isHashed()` and `Hash::verifyConfiguration()`, as Eloquent's `hashed` cast); `hashed(false)` leaves the hashing to the repository. `Field::omits()` lets a field leave a value out of the values saved. The Rector rules convert the 1.x `Password` to `Password->hashed(false)`, with a TODO, since 1.x projects hash in their repository, and `--check` lists the `Password` fields that say neither `hashed()` nor `hashed(false)`, in every source folder; the 1.x confirmation is gone (#128, #140, #141).
- `Text::secret()`, sent as `settings.secret`: the value is a secret, typed in a password input and shown as dots. `Text::writeOnly()` never sends the stored value back (items have `null`), and an update that leaves it out or sends it empty keeps it, for a secret the project keeps encrypted (folkloreinc/panneau-js#143).
- A `tone` on the options of `Select`, `Tags` and `SelectFilter`, which colors them as badges in lists and details: a key of an option, or the `tone()` method of an enum case, as `label()` (folkloreinc/panneau-js#144).
- Lists paged with cursors: `$list->cursorPagination()`, sent as `list.pagination: "cursor"` when the repository is `CursorPaginated`, as `EloquentRepository` is (`cursorPaginate()`). The list then reads `cursor` instead of `page` (`ResourceQuery::$cursor`), calls `cursorQuery()`, and answers `meta: { perPage, nextCursor, previousCursor }` without a total; an unreadable cursor is a `422` with `errors.cursor`. A Scout search pages by number behind its cursors. Numbered pages stay the default (panneau-js decision 0039, folkloreinc/panneau-js#150).
- The built-in item actions of panneau-js decision 0040: `show`, `edit`, `duplicate` and `delete` exist on every resource without being declared, and the app shows each one by the abilities of each item. Items now have `duplicate` next to `view`, `update` and `delete`: the policy's `duplicate()`, or else `create`, and `false` unless the resource opts in with `duplication()`, the repository is `Duplicable`, the app has the duplicate route, and the user can view the item. `Resource::itemActions()` says which ones the app shows, in which order (`null`, the default, for the four, left out of the definition); it only hides buttons, and an unknown id throws. The built-in `delete` answers `POST /resources/{resource}/actions/delete` for every resource, authorized by the policy's `delete`; `DeleteAction` is only declared to replace it. Needs `@panneau/*` with decision 0040 (#142).
- Duplicating, for the resources that opt in: `Resource::duplication()` returns what a copy includes, `Duplication::make()->relations([...])`, and `null` by default, which leaves `duplicate` off. `POST /resources/{resource}/{id}/duplicate` copies the item through its `Duplicable` repository and answers `201` with the copy and its `ETag`. `EloquentRepository` copies the attributes (`replicate()`) and the listed relations: `BelongsToMany` and `MorphToMany` with their pivot values, `HasOne`/`HasMany` and their morph versions by copying the children, and the relations of the children with dot paths. Unique slugs get a free value (`-copy`, `-copy-2`…); another unique value is a `409` problem. The resource's new `duplicating($item, $copy, $request)` hook changes the copy before it is saved, and `duplicated($item, $copy, $request)` runs after, all in one transaction. A relation the repository can't copy throws when the definition is built (`Duplicable::checkDuplication()`), not on a click. Duplicating needs `view` on the item. `resourceRoutes(only: [...])` takes `duplicate` (#142).
- `EditAction` and `ShowAction`, to replace the built-in links: `EditAction::make()->url(...)`, `->route(...)`, `->label(...)`. The ids `show` and `edit` are reserved for links (#142).
- `Resource::open()`: `'panel'` (the default, left out of the definition), or `'edit'`, sent as `"open": "edit"`, so that the title of an item opens its form, and its show route redirects there; another value throws (#142).
- The page of the app writes `<meta property="csp-nonce">` when the request has a CSP nonce of Vite (`Vite::useCspNonce()`), so that `@panneau/app` and React Aria use it; views get it as `$nonce` (folkloreinc/panneau-js#152).

### Changed

- `panneau:install` writes `@panneau/app` `^5.0.0-alpha.10` in `package.json`, the first alpha that applies the rules limited to a mode (#140).

### Fixed

- `Panneau::routes()` moves the routes of the site registered before it, outside Panneau's path, after its own: a page route of the site such as `/{page}`, loaded first by a `RouteServiceProvider` listed before the provider that loads `routes/panneau.php`, caught `/panneau`. The site's routes under Panneau's path keep their precedence (#131).
- The definition's `user` has the name and email of users that expose them as methods (`name()`, `email()`), as the entities of laravel-folklore, instead of an empty name (#135).
- `panneau:install --upgrade --check` lists only what is left of 1.x on a resource that already extends a 5.x resource, instead of every method as `manual`; and once `package.json` has only `@panneau/*` 5, it no longer lists the files that import 5.x modules (#129).
- Each run of the Rector rules gets a cache of its own: Rector's shared cache skipped the files it had seen unchanged, after a fix by hand of a parent class or with a newer release of the rules (#130).
- The Rector rules keep the row buttons of a 1.x `index()`: the `show`, `edit`, `duplicate` and `delete` of its `actions` column (on the column of a field too) become `itemActions()`, in their order, so that the list shows what it showed in 1.x, and no `duplicate` it didn't have; `withoutActionsColumn` gives `[]`. Nothing is written for what the 5.x default shows (`show`, `edit` and `delete`, with `duplicate` or not, since it needs `duplication()`), nor without an `actions` column, and a column whose buttons aren't a literal list gets a TODO. The rules no longer write `DeleteAction` in `actions()`, and no longer drop `show` and `edit`. Without `show`, a TODO suggests `open(): 'edit'`, and a 1.x list with a duplicate button gets a TODO to opt in with `duplication()`. `--check` says what the buttons become, and lists the duplicate of a 1.x list once, as `manual` (#142).
- `--check` lists a concrete 1.x base of resource types that no resource lists, and an indented `on:` key of a workflow (a step's `with:`) no longer hides the `branches:` under it (#126).

## [5.0.0-alpha.7] - 2026-10-30

Goes with `@panneau/*` `5.0.0-alpha.7`.

### Fixed

- `panneau:install --upgrade --check` lists the references to 1.x contracts that 5.x doesn't have (`Panneau\Contracts\ResourceItem`, `Repository`…) anywhere in the project, with their file and line: an entity contract that extends `ResourceItem` breaks the site, not only the admin, once 5.x is installed. Files that another section lists (resource types, controllers, files to delete) are left out, and the other uses are counted by file (#122).
- `panneau:install --upgrade` writes the JS entry in the project's JS directory, `resources/assets/js/panneau.tsx` when the project's JS is there, with the same path in the Vite config and `app.entry`. The directory is the one with the entry of an earlier run, the most `@panneau/*` imports, or the one `package.json` or `tsconfig.json` names (#123).
- `--check` no longer reports a workflow branch named after Panneau (`branches:` and `tags:` under `on:`), and the Rector rules replace `translationsNamespace()`, which 5.x never calls, with a TODO that names its keys for `pluralLabel()` and `singularLabel()`, or keep it with the TODO when the resource calls it (#124).

## [5.0.0-alpha.6] - 2026-10-30

Goes with `@panneau/*` `5.0.0-alpha.7`.

### Added

- `php artisan panneau:install --upgrade` migrates a 1.3 or 1.4 project: it converts `config/panneau.php`, writes the service provider, routes file and JS entry (Vite next to the site's bundler), moves a 1.x override of the page aside, keeps what it changes as `{file}.1x.bak` (never overwriting a backup), and lists what to move by hand and the files the package now provides. `--upgrade --check` reports what the project has and what each part becomes, without writing: resources, types, fields, controllers, routes, resolvers, request macros, build and deployment files. It reads the project without running it ([decisions/0013](docs/decisions/0013-upgrade-from-1x.md), [migration guide](docs/migration.md), #114, #118).
- Rector rules for 1.x resources and fields, in `rector.php`: static properties to methods, `index()` to `list()`, field classes and builder methods to 5.x, and `// TODO(panneau-5)` comments where they can't convert (overridden `make*()` methods, methods the 5.x class doesn't have), which `--check` lists ([migration guide](docs/migration.md#5-run-the-rector-rules), #115, #119).
- `RichText` settings, for the rich text field of `@panneau/editor`: `toolbar()`, `headings()`, `style()` for block styles (a class on an element, `<p class="big">`), and `linksInNewTab()`, which refuse what the protocol refuses (folkloreinc/panneau-js#128, #120).

### Changed

- `folklore/laravel-panneau` requires `nikic/php-parser` ^5.0, for the upgrade (#114, #118).

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

[Unreleased]: https://github.com/folkloreinc/laravel-panneau/compare/v5.0.0-alpha.8...v5.x
[5.0.0-alpha.8]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.8
[5.0.0-alpha.7]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.7
[5.0.0-alpha.6]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.6
[5.0.0-alpha.5]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.5
[5.0.0-alpha.4]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.4
[5.0.0-alpha.3]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.3
[5.0.0-alpha.2]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.2
[5.0.0-alpha.1]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.1
