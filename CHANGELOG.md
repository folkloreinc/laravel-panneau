# Changelog

All notable changes to `folklore/laravel-panneau` 5.x are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/). The releases of 1.3 and 1.4 are on [GitHub](https://github.com/folkloreinc/laravel-panneau/releases).

5.0 is released as pre-releases first (`5.0.0-alpha.N`, then beta and RC), each with the `@panneau/*` version it goes with. Until the release candidate, a pre-release can break the previous one: each one says how to upgrade from the previous one. The GitHub release of each version has the details and examples.

## [Unreleased]

### Added

- The dashboard of the home page (panneau-js decision 0064, #178): `Panneau::dashboard()` declares its widgets, per user, with `Widget::stat()`, `Widget::series()`, `Widget::list()` and `Widget::make()` for the widgets of plugins. A `stat` or a `list` can read the list of a resource (`resource()`, checked when the definition is built); the others get their data from a `WidgetData` class or a closure (`using()`), at `GET {path}/api/dashboard/widgets/{id}`, which answers the widgets of the user's dashboard only, checks the shape of their data (a `500` that names the wrong keys in the log), and keeps it for `cache($seconds)` per user, locale and context. With widgets, the home page is the dashboard. Needs `@panneau/*` with the dashboard widgets.

### Changed

- The image of the items of a media library (`MediaResource::itemImage()`) is their `media`: its list offers the gallery, and the table shows their thumbnails, with the clients that don't default to it yet (panneau-js decision 0061, folkloreinc/panneau-js#231). A library can return another field.

## [5.0.0-alpha.12] - 2026-11-04

Goes with `@panneau/*` `5.0.0-alpha.17` (`5.0.0-alpha.16` works too: the sortable column headers are already in it).

Lists that declare no sorts can now be sorted by their columns.

### Breaking changes

- A list without `sorts()` now accepts the `sort` param for the sortable fields of its columns, and its headers sort them. To keep a list unsortable, call `sorts([])`.

### Changed

- A list that doesn't declare its sorts (`sorts()`) sorts by the fields of its columns whose values sort as they are stored, and its column headers sort: text, numbers, dates, durations, amounts, booleans and single choices, in one locale, and not computed by `resolveUsing()` (`Field::isSortable()`). `EloquentRepository` leaves out accessors, relations, and ranges and amounts without their casts (the new `SortsFields` contract). `sorts([])` turns sorting off, and a column marked `sortable()` now adds its field to the sorts the query accepts (#176).

## [5.0.0-alpha.11] - 2026-11-04

Goes with `@panneau/*` `5.0.0-alpha.16`, which it needs: the `daterange`, `money`, `address` and `link` types, the `postalCode` and `providers` rules, and the new settings of `duration`, `tel`, `country` and `color` are new in that release.

New field types and better versions of existing ones, all additive: date ranges and money, addresses and links, durations in a declared unit, phone extensions, several countries, the providers of an embed and color palettes.

### Added

- `DateRange` and `Money` fields, the `daterange` and `money` types of the protocol (panneau-js decision 0053, folkloreinc/panneau-js#198, folkloreinc/panneau-js#200). A range is `{ start, end }`, days or with `time()` date-times, with `allDay()`, `open('start'|'end'|'both')`, `timeZone()` and `chooseTimeZone()`; an amount is `{ amount, currency }`, an integer in the minor unit of an ISO 4217 currency, with `currency()` or `currencies()`. The types imply the `daterange` and `money` rules, which the server enforces with a message for each failure (a missing start or end, an end before the start, an amount that isn't an integer, a currency that isn't allowed); `min()` and `max()` of a `Money` apply to the amount, in minor units.
- The `AsDateRange` cast (`AsDateRange::class.':starts_at,ends_at'`, with optional columns of the zone and of `allDay`, or `AsDateRange::columns()`) and the `AsMoney` cast (`AsMoney::class.':price_cents,currency'`, or a fixed currency, `':price_cents,CAD'`), and `Panneau\Money\Money`, a value object with `fromDecimal()` rounded to the digits of ISO 4217 (`Panneau\Money\Currency`, the table of `@panneau/protocol`), `toDecimal()` and `format()`.
- On an attribute with the `AsDateRange` cast, `EloquentRepository` reads a `date-range` filter as an overlap of ranges, open bounds included, and sorts by the start column; it sorts an `AsMoney` attribute by its amount. Lists show `daterange` and `money` fields by default, and calendars take a `daterange` field.
- The `Sessions` preset: a `Repeater` of `dates` (a `DateRange` with `time()`) and, with `venues()`, a `venue` relation, to which `append()` adds fields.
- `php artisan panneau:convert-money`, which turns the amounts that a project kept in major units (floats, decimals) into minor units: from a column to an integer column, or a key of a JSON column into a money value, in the currency of `panneau.money.currency` (`--currency`), with `--dry-run` and `-v`. `panneau:install` writes `DateRange` and `Money` fields for the attributes of these casts, and `--check` suggests them for two date fields named as a start and an end, and for a `Number` with a currency in its strings.
- The `Address` field, the `address` type of panneau-js decision 0054: `{ street, unit, city, region, postalCode, country }`, and `point` with `point()`, with `country()`, `countries()`, `preferred()` and `requiredParts()`. Once a street, a unit, a city, a region or a postal code is filled (a country alone is no address, and saves as `null`), the backend checks the required parts, the country and a postal code of the address's country, each on the path of its part (`address.postalCode`), and saving normalizes the parts (the country in capitals, the regions of Canada and the United States as codes, the postal code in the format of its country) (folkloreinc/panneau-js#199).
- The protocol's `postalCode` rule (`Panneau\Rules\PostalCode`, `Text::postalCode()`), with a country code or another field of the form: Canadian and US postal codes, once normalized (panneau-js decision 0054).
- `php artisan panneau:convert-addresses` turns the parts of addresses kept in separate columns (`panneau.addresses.columns`, `panneau.addresses.country`) into address values in a JSON column, and the upgrade from 1.x points the groups of a street, a city and a postal code to `Address` (panneau-js decision 0054).
- The `Link` field, the `link` type of panneau-js decision 0055: `url`, `page`, `email`, `tel`, `file` and a site's types (`types()`), with `pages()`, `libraries()`, `newTab()`, `linkLabel()` and `resolvePageUrlUsing()`. The backend checks the parts of the type on their paths (`link.url`, `link.page`), keeps only those, sets `newTab`, and items give the reference and the URL of the page or the file of the links at the top of a resource. The upgrade from 1.x points the groups of a label, a URL and a new tab toggle, and the `Url` fields that accepted `mailto:`, to it (folkloreinc/panneau-js#202).
- Lists without columns show `address` fields, with the other simple types.
- `Duration::unit()` and `Duration::largestUnit()`, and `Duration::precision()` takes `hours` and `days` too (panneau-js decision 0056): a duration is a whole number of its unit, `seconds` (the default), `minutes`, `hours` or `days`, and the admin's input can show days. The three refuse another unit with an `InvalidArgumentException`. The server still checks a whole number, 0 or more (folkloreinc/panneau-js#211).
- Phone extensions (folkloreinc/panneau-js#212, panneau-js decision 0057): `Tel::extension()` allows an extension after `;ext=`, `+15145550199;ext=123`, and the `tel` rule with `extension` accepts it. `PhoneNumber::toE164()` keeps the extension typed after a number when asked (`ext. 123`, `x123`, `poste 123`), and `splitExtension()`, `withoutExtension()` and `extension()` read them. In `panneau.phones.columns`, `'table.column' => ['extension' => true]` makes `panneau:convert-phones` keep those extensions, and `['extension' => 'its_column']` merges an extension kept in another column.
- Several countries, the providers of an embed, and color palettes (panneau-js decision 0058): `Country::multiple()`, a list of distinct allowed codes in their order (folkloreinc/panneau-js#213); `Embed::providers([...])`, the portable `providers` rule, which resolves the URL during validation and refuses one it can't resolve or whose provider isn't listed (folkloreinc/panneau-js#214); `Color::palette([...])`, the colors to pick from, whose values are the only ones accepted (folkloreinc/panneau-js#215).

## [5.0.0-alpha.10] - 2026-11-04

Goes with `@panneau/*` `5.0.0-alpha.15`, which it needs: the pages of the app for password reset, email verification, two-factor authentication, the account and passkeys, and the buttons of OAuth providers, are new in that release.

The whole auth of the `panneau` mode, each part off by default: password reset and email verification, two-factor authentication that a site can require by config, ability or role, the account page, passkeys and OAuth providers.

### Breaking changes

- Requires `@panneau/*` `5.0.0-alpha.15` or later (see above).
- `laravel/passkeys` (`^0.2`) is now a requirement, as Fortify requires it.

### Added

- OAuth providers on the login page of the `panneau` auth mode (panneau-js decision 0052, decision 0020), with Socialite (suggested): `panneau.auth.providers` (label, allowed `domains`, `scopes`, `with`, `driver`), and `auth.providers` in the definition with the URL of their `GET auth/providers/{provider}/redirect`. The callback needs a verified email (Google's `email_verified`), in an allowed domain and, for Google, with its `hd` claim in it, and the user with this email (or the one of a `Panneau\Contracts\Auth\ResolvesProviderUsers` in `panneau.auth.provider_users`) allowed in by the gate. It goes to `next`, or back to the app with `authError` (#34).
- Passkeys in the `panneau` auth mode (panneau-js decision 0051, decision 0019), with `panneau.auth.passkeys`, on `laravel/passkeys` (now required, `^0.2`, as Fortify requires it) without its routes: `POST auth/passkeys/options` and `POST auth/passkeys/login` log in with a passkey, without a second factor; `GET` and `POST account/passkeys`, `POST account/passkeys/options` and `DELETE account/passkeys/{passkey}` list, add and remove the passkeys of the account, behind a password confirmation. The user implements `PasskeyUser` with `PasskeyAuthenticatable` (#163).
- The account of the current user in the `panneau` auth mode (panneau-js decision 0050, decision 0018): `GET` and `PATCH account` (the name and the email) and `PUT account/password` (with `currentPassword`), and `auth.account` in the definition. They run the Fortify actions of `panneau.auth.update_profile_using` and `panneau.auth.update_password_using`; Panneau's defaults save an Eloquent user, and send Panneau's verification link to a new email with `email_verification` (#161).
- Two-factor authentication in the `panneau` auth mode (panneau-js decision 0049, decision 0017), with `panneau.auth.two_factor`, on Fortify's `TwoFactorAuthenticatable` trait, actions and TOTP provider, without Fortify's routes: a password login answers `202` for a user who has it, and `POST auth/two-factor/challenge` ends it with a code or a recovery code; `GET`, `POST` and `DELETE account/two-factor`, `POST account/two-factor/confirm` and `POST account/two-factor/recovery-codes` set it up, confirm it, make new recovery codes and turn it off. `panneau.auth.two_factor_required` (`true` or a Gate ability) and the roles of the roles module with `'two_factor' => true` require it: those users get a `403` problem of type `urn:panneau:problem:two-factor-required` until it is on. Its changes need a recent password: a `423` problem of type `urn:panneau:problem:password-confirmation`, and `POST auth/password/confirm` (`panneau.auth.password_timeout`) (#160).
- Password reset and email verification in the `panneau` auth mode (panneau-js decision 0048, decision 0016), off by default. `panneau.auth.password_reset` adds `POST auth/password/forgot` and `POST auth/password/reset`: the link is sent with `Panneau\Auth\Notifications\ResetPassword` to users that the `viewPanneau` gate allows in, opens the page of the app (`{path}/auth/reset-password`), and the new password is set by the Fortify action of `panneau.auth.reset_password_using` (`Panneau\Auth\ResetUserPassword` by default) on the broker of `panneau.auth.passwords`. `panneau.auth.email_verification` refuses users whose address isn't verified (`MustVerifyEmail`) with a `403` problem of type `urn:panneau:problem:email-unverified`, serves them the page of the app without the definition, and adds `POST auth/email/verification` (sends `Panneau\Auth\Notifications\VerifyEmail`, whose link opens `{path}/auth/verify-email`) and `POST auth/email/verify`. The site's own notifications, their URLs and Fortify's routes are left alone. Both are throttled by `panneau.auth.rate_limit` (#158).

## [5.0.0-alpha.9] - 2026-11-03

Goes with `@panneau/*` `5.0.0-alpha.13`, which it needs: the pages and menus, the current context, the abilities of pages and fields, hidden columns, the calendar and the `country`, `region` and E.164 `tel` types are new in the protocol of that release.

What the contests platform needs for its app shell, its roles and its lists: pages and menus in the definition, a current context with values fixed by the request (the client of its domain), abilities of fields and pages, an optional roles module, hidden columns, inline editing, the calendar and the gallery, and the `Country` and `Region` fields with phone numbers in E.164.

### Breaking changes

- Requires `@panneau/*` `5.0.0-alpha.13` or later (see above).
- `Tel` values are phone numbers in E.164 (`+15145550199`): the type implies the protocol's new `tel` rule, and the server refuses any other value. Convert the numbers kept as typed, or their forms refuse them until they are retyped (folkloreinc/panneau-js decision 0047).

### Added

- Pages and menus of the app shell in the definition, as `pages` and `menus`: declared in `config/panneau.php` (`pages`, `menus`, in the protocol's shape) or in a service provider with `Panneau::pages([...])` and `Panneau::menu('main', [...])`, which take the builders `Panneau\Navigation\Page` and `Panneau\Navigation\MenuItem` (`resources()`, `resource()`, `page()`, `link()`, `separator()`, `group()`, and `make()` for the types of plugins) or arrays, or a closure called through the container with the user as `$user` each time the definition is built, for labels in the request's locale and menus that depend on the user. The service provider wins over the config, for a page of the same id or a menu of the same name. Resource classes in menus become ids, and an entry that names a resource that isn't registered, a page that isn't declared or a page with `params` throws a `LogicException` when the definition is built. Without pages or menus, the definition is unchanged (#33).
- A current context, such as a client then a contest (panneau-js decision 0045): declared with `Panneau::context([...])`, `Panneau\Context\ContextParam` builders (`label()`, `required()`, `allLabel()`, and `resource()`, `options()`, or `search()` and `find()`, `default()`, and `fixed()` for a value that the request fixes, such as the client of its domain, which another value in the header can't widen) or a closure. The `ResolveContext` middleware (`panneau.context`), on every route of the API, checks each value of the `Panneau-Context` header for the user, in order (a param of a resource through its repository, its scope and its `view` policy), and answers a `403` problem of type `urn:panneau:problem:context` with its `param` for a value the user can't use. Resources read the values in `scope()` with `$this->context()` (`Panneau\Context\Context`: `get()`, `getOrFail()`, `has()`, `all()`, `label()`, `item()`), and menus and pages declared as closures get them. The definition has `context`, built for the values; `GET /context/{param}/options?q=` gives the choices of a param; the page of the app inlines the definition for the values in the prefix of its path, and `Panneau::bootConfig()` takes a `path`. Responses vary with the header. Without a context, nothing changes (#147).
- Abilities of fields: `canSee()` and `canUpdate()` take a Gate ability or a closure, and give the field `"abilities": { "view": false }` or `{ "update": false }` in the definition of the users without it. Items leave out the values of the fields the user can't see, in lists, items and the search, and saving ignores those of the fields they can't see or edit (panneau-js decision 0046, #148).
- A policy with `deleteAny()` hides the built-in `delete` when it refuses: the definition declares it with `"abilities": { "run": false }`, and its route answers `403` (#148).
- A policy with `runAction($user, ?object $item, string $action)` answers every action of its resource, on items and on the resource (#148).
- An optional roles module (decision 0014, #149), off by default (`panneau.roles.enabled`): roles and their permissions in config (`contests.update`, `contests.*`, `*`), roles and grants of users in a `RoleStore` (two tables by default, `panneau-roles-migrations`), the `Roles` and `Grants` fields and `panneau:role` to assign them, a `Gate::before()` that answers the permissions of Panneau and nothing else, `ResourcePolicy` as the base of the policies of resources, and ownership: users whose roles are all `owned` reach only the items of `OwnedResource::ownerScope()`, on every route. `panneau:install` generates a `ResourcePolicy` when the module is on.
- `Field::isStored()`: a field that keeps its value elsewhere returns false, and saves it from `prepare()` (#149).
- `Page::can()` takes a Gate ability or a closure: a page the user can't open has `"abilities": { "view": false }` in the definition, and the app hides it (panneau-js decision 0046, #149).
- `Column::hidden()`, a column the list doesn't show until the user shows it, and `Column::hideable(false)`, one the user can't hide (panneau-js decision 0042, #150).
- `ResourceList::calendar()`: the items on the days of a date field, in the `calendar` layout of the list, with the list's `date-range` filter on that field; and `Resource::itemImage()`, the field shown as the image of an item, in the `gallery` layout (panneau-js decision 0044, #150).
- `php artisan panneau:convert-phones` turns the phone numbers that a project kept as typed into E.164, in the columns and JSON keys of `panneau.phones.columns`, without a library of numbering plans; `Panneau\Fields\PhoneNumber` converts and formats them, and `panneau.phones.country` is the country of the `Tel` fields without `country()` (folkloreinc/panneau-js decision 0047).
- `Country` and `Region` fields, the `country` and `region` types: a country is an ISO 3166-1 alpha-2 code (`Country::CODES`, with `XK`), limited with `countries()` and ordered with `preferred()`; a region is a string, whose country comes from the field that `from()` names or from `country()`. `Tel::country()` sets the country of a new number. Lists show `country` and `region` fields without columns (folkloreinc/panneau-js decision 0047).

### Fixed

- A partial update reads the `visibleWhen` of a field with the item's values of the fields it doesn't send: a field shown when `status` is `published`, sent alone, as a list edits it in place, was dropped without an error. It is now saved on a published item (panneau-js decision 0043, #150).

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

[Unreleased]: https://github.com/folkloreinc/laravel-panneau/compare/v5.0.0-alpha.12...v5.x
[5.0.0-alpha.12]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.12
[5.0.0-alpha.11]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.11
[5.0.0-alpha.10]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.10
[5.0.0-alpha.9]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.9
[5.0.0-alpha.8]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.8
[5.0.0-alpha.7]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.7
[5.0.0-alpha.6]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.6
[5.0.0-alpha.5]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.5
[5.0.0-alpha.4]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.4
[5.0.0-alpha.3]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.3
[5.0.0-alpha.2]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.2
[5.0.0-alpha.1]: https://github.com/folkloreinc/laravel-panneau/releases/tag/v5.0.0-alpha.1
