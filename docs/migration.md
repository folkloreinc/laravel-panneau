# Migration (Laravel)

The migration strategy, order and v1 → 5 mapping are in [`panneau-js/docs/migration.md`](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/migration.md). This page adds the Laravel steps, for a project on 1.3 or 1.4. The tools never run the project's code: they read its files, since its 1.x classes extend classes that 5.x no longer has. See [decisions/0013](decisions/0013-upgrade-from-1x.md).

## 1. Before

- **Commit everything.** The upgrade keeps a backup of what it replaces, but the diff is the best review.
- **Upgrade the project's versions first.** It needs PHP 8.3+ and Laravel 12+ (see [decisions/0006](decisions/0006-supported-versions.md)), and Node 20.19+ or 22.12+ for Vite 8.
- **Take the 1.x service provider out of the providers** (`config/app.php` or `bootstrap/providers.php`): comment its line, and keep the file. It calls 1.x classes at boot (`LocalizedField::setLocalesResolver()`, `Upload::setEndpoint()`), so the app, and `artisan`, can't start with it once 5.x is installed. `--upgrade` puts the 5.x provider on that line.

## 2. Require the new versions

```bash
composer require folklore/laravel-panneau:^5.0@alpha folklore/laravel-folklore:^2.0@alpha
```

Require them in the same command: `composer require` runs `package:discover`, which boots the app, and the app can't boot with `laravel-panneau` 5 and `laravel-folklore` 1.1 together. `laravel-folklore` 2's Panneau adapter maps resources to their repositories; projects without it leave it out.

## 3. Audit the project: `--check`

```bash
php artisan panneau:install --upgrade --check
```

It writes nothing, and works at every step of the migration. It lists what the project has and what each part becomes in 5.x, with its file and line, or `manual` when it is moved by hand:

| Section | What it reads |
|---|---|
| Packages | `folklore/laravel-panneau` and `laravel-folklore` in `composer.json`; the `@panneau/*` 4 packages in `package.json`, which `@panneau/app` 5 replaces, and the files of `resources/js` that import them: the site's bundle stops building once `@panneau/app` is 5, until the JS codemods move them |
| Config | Each key of `config/panneau.php`: `name`, `intl.locales` → `locales`, `routes.prefix` → `path`, `routes.middleware` → `middleware`; keys 5.x drops (`intl.translations`, `routes.map`, `only_declared_fields`…); keys that aren't 1.x keys, kept |
| Resources | Those of the config, and other subclasses of `Panneau\Support\Resource`: their static properties (`$repository` → `repository()`, `$jsonResource` → `toItem()`, which resolves it (`jsonResource()` is not built yet), `$types`, `$settings`…), their methods, and their `index()`: columns, sorts, filters and actions → `list()` and `actions()`. The 1.x id is camel case (`jobListings`), the 5.x id kebab case (`job-listings`): keep the 1.x one with `id()` if URLs or fields name it |
| Resource types | Subclasses of `Panneau\Support\ResourceType`, with the resources that list them: block types become `BlockType`s of a `Repeater`; other types wait for `types()` |
| Field classes | The project's fields, grouped by the 1.x class they extend, with the component they send to the JS: `Items` → `Repeater`, `Fields` → `Group`, `ResourceItem(s)` → `Relation`, `*Localized` → `->localized()`, built-in fields, the fields of `laravel-folklore` 1.1. A direct subclass of `Field`, or a component that only the project's JS knows, is `manual`: a built-in type, or a field type with a JS plugin |
| Fields used | How many times each 1.x field class is used, and what it becomes. Classes that 5.x has under the same name (`Text`, `Select`, `Image`…) are left out |
| Resource controllers | Subclasses of `ResourceController`, the resources that use them, and their methods: `getIndexQueryFromRequest()` → `scope()` or list filters, `getStoreDataFromRequest()` → `creating()`, other public methods → actions or plugin pages |
| Routes | `routes/panneau.php`: `Panneau::router()->resources()` → `Panneau::routes()`, the generated routes (home, upload, account) the package provides, custom routes (`manual`), the guard of the `auth` middleware, `can:view` → the `viewPanneau` gate, other middleware, and Panneau's own login (`Panneau::router()->auth()`) |
| Route names | `route('panneau.…')`, `to_route()`, `redirect()->route()` and `URL::route()` calls in PHP and Blade: 5.x routes are `panneau.app` and `panneau.api.*` |
| Service providers | The 1.x provider and where it is registered; static resolvers (`LocalizedField::setLocalesResolver()`, `Upload::setEndpoint()`, `PageSlug::setRoutesResolver()`), `Panneau::serving()`, the `Panneau::class` policy, composers of `panneau::app` |
| Files to delete | What the package now provides: `HomeController`, `AccountController`, `UploadController` and their requests, `PanneauPolicy`, the overridden views and translations of `vendor/panneau`. `AppComposer`, `PanneauComposer` and definition classes are `manual`: what they add to the page (props, menus, a home component) moves by hand |
| Request macros | `isPanneau()`, `isPanneauIndex()`, `isInPanneau()`… and `panneau_route()`, usually in API resources, auth controllers and middleware. They are `manual`: 5.x has no such macros, so each call throws until it becomes `$request->routeIs('panneau.*')` |
| Build and deployment | The site's bundler, the `build` script, `Envoy.blade.php` (the built files it copies need `public/build/`), Node versions in `.github/workflows`, `.nvmrc` and `engines`, composer scripts and Envoy lines that name Panneau, `.gitignore` |

## 4. Upgrade: `--upgrade`

```bash
php artisan panneau:install --upgrade
```

It writes:

- **`config/panneau.php`**, the 5.x config with the 1.x values that have a 5.x key, written as the code they were (`env()` calls stay calls). Keys that aren't 1.x keys are kept at the end. The project's own middleware of the 1.x routes and config is kept; the session, auth and gate middleware (`web`, `auth`, `can:view`) is replaced by `['web', 'panneau.auth']`. Auth is `external`, on the guard of the 1.x `auth:{guard}` middleware.
- **`app/Providers/PanneauServiceProvider.php`**, which registers the resources of the 1.x config with `Panneau::resources()`, defines the `viewPanneau` gate (closed: nobody gets in until the project opens it) and loads the routes. It replaces the 1.x provider's line in `config/app.php` or `bootstrap/providers.php` (by its full name, or its short name when the file imports it), commented or not, or is added to `bootstrap/providers.php`. Once the config is 5.x, the provider isn't written again.
- **`routes/panneau.php`**: `Panneau::routes()`.
- **The page.** A 1.x override of `resources/views/vendor/panneau/app.blade.php` would shadow the package's page: it is moved to `app.blade.php.1x.bak`. Move what it added (fonts, scripts) by hand, in a view of the project named in `panneau.app.view`.
- **The JS entry** (`resources/js/panneau.tsx`) and Vite. A project that builds its site with another bundler (`@folklore/cli`, webpack, Mix) keeps it: `vite.config.mjs` builds the admin only, into `public/build`, and `package.json` gets `vite`, `laravel-vite-plugin`, the `build:panneau` and `dev:panneau` scripts, and `npm run build:panneau` at the end of `build`. A project on Vite gets the entry in its config. `@panneau/app` 4 becomes 5; the other `@panneau/*` 4 packages stay until the JS codemods move their imports. `--no-js` skips this part.

**It never loses a file.** A file it changes (the config, the routes, the provider at the same path, the view, the provider list, `package.json`, the Vite config) is kept next to it as `{file}.1x.bak`. A file it doesn't recognize as 1.x is kept, unless `--force`. When `{file}.1x.bak` is already there with other contents, it keeps the file, or with `--force` backs it up again as `{file}.1x.bak.2`: a backup is never overwritten. Running it again changes nothing.

It then lists what is left to move by hand (the `manual` findings of `--check`), and the 1.x files the package replaces. It asks before deleting them, without a backup; without a terminal (`--no-interaction`), it keeps them.

## 5. Run the Rector rules

```bash
composer require --dev rector/rector
vendor/bin/rector process app/Panneau --config vendor/folklore/laravel-panneau/rector.php
vendor/bin/pint app/Panneau
```

Run them on the directories that hold the resources and fields (`app/Panneau` usually), and review the diff: they import the class names they write and remove unused imports in every file they process, so other directories would change too. Like the audit, they read the project's parents from its files: run them from the project's root. They change nothing on a second run.

| Rule | 1.x | 5.x |
|---|---|---|
| `ResourcePropertiesRector` | `extends Panneau\Support\Resource` | `extends Panneau\Resources\Resource` |
| | `$repository` | `repository()` |
| | `$jsonResource` | `toItem()`, which resolves the `JsonResource` |
| | `$types` | `types()`, with a TODO: resource types are not built yet |
| | `$settings` | Defaults removed; `canCreate` false and `hideInNavbar` true become TODOs; other keys go to `settings()` |
| | `$controller`, `$index`, `$attributes`, `$components`, `$forms` | TODOs |
| | A property that `makeRepository()`, `makeJsonResource()` or `makeJsonCollection()` overrides, or that the class reads (`static::$repository`) | Kept next to the 5.x method, with a TODO on both and on the override: 5.x never calls the override |
| | `$jsonCollection`, `$onlyDeclaredFields` | Removed: lists serialize each item with `toItem()`, and only declared fields are saved |
| | `name()` | `pluralLabel()` |
| `ResourceIndexRector` | `index()` returning an array | `list()` with the columns that are fields and the sortable ones, `actions()` with `DeleteAction` for a `delete` action. `index()` stays, with a TODO listing what's left (filters, search, other actions and columns), or is removed when nothing is left |
| `FieldCallsRector` | `TextLocalized::make('title')`, and the other `*Localized` fields | `Text::make('title')->localized()` |
| | `Text` and `TextLocalized` with `isTextarea()` | `Textarea` |
| | `Html`, `Toggle`, `Telephone`, `Radios` | `RichText`, `Boolean`, `Tel`, `Select` |
| | `Upload`, `Media`, `Image`, `ImageUpload`, `Video`, `Audio`, `Document` and their plurals | `Media` or `Image`, with `accept(['video/*'])`, `accept(['audio/*'])` or `multiple()`; documents get a TODO to name their library |
| | `Items`, `Fields`, `Item`, `ResourceItem`, `ResourceItems` | `Repeater`, `Group`, `Relation`, `Relation->multiple()` |
| | `PageSlugLocalized` of `laravel-folklore` 1.1 | `PageSlug`, localized by default |
| | `make('name', 'Label')`, `new Text('name')` | `Text::make('name')->label('Label')` |
| | `withTransLabel()`, `withLabel()`, `withTransHelpText()`, `withHelpText()` | `label(__())`, `label()`, `help(__())`, `help()` |
| | `isRequired()`, `isDisabled()`, `withFields()`, `withDefaultValue()`, `withOptions()`, `withRules()`, `withSettings()`, `withResource()` | `required()`, `readOnly()`, `fields()`, `default()`, `options()`, `rules()`, `settings()`, `resource()` |
| | `hideInForm()`, `onlyForTypes()`, `exceptForTypes()`, `onlyTypes()`, `exceptTypes()`, `withAttributes()`, `withComponents()`, `withSiblingFields()`, `withQuery()`, `withTypes()`, `withoutType()`, `isRequired([...])` with locales | Removed, with a TODO |
| | `withButton()`, `withoutReset()`, `uploadOnly()`, `withEndpoint()`, `isNullable()`, `isEnabled()`, `isText()`, `showInForm()` | Removed: they only changed the 1.x UI, or were defaults |
| `ClassesToMoveByHandRector` | Subclasses of `ResourceType`, of 1.x fields, and of `ResourceController` | A TODO on the class: they keep their 1.x parent until they're moved by hand |

The field rules apply to chains that make a 1.x field, or a field of the project that extends one; a method that the project's field class declares itself is kept. On a 1.x field class that becomes a 5.x class, a method the 5.x class doesn't have (`withFieldAttributes()`, `asItem()`, `isMultiple()`…) is removed with a TODO, since it would throw. Where a rule can't convert, it leaves a `// TODO(panneau-5): …` comment, and `--check` lists them under "Left by the Rector rules".

## 6. Move the rest by hand

`--check` lists it; run it again as you go.

- **Resources:** Folklore projects point each resource to its repository contract (`repository()`), and the `laravel-folklore` adapter does the rest; static `$repository` and `$jsonResource` become `repository()` and `toItem()`.
- **Custom controllers** become hooks and `Action` classes: exports and imports `ExportAction` and `ImportAction`, other operations on items custom actions.
- **Custom routes** of `routes/panneau.php` (kept in its backup) become actions or plugin pages.
- **The gate.** Port the `view()` of the 1.x `PanneauPolicy` to the `viewPanneau` gate of the new provider.
- **The login page.** 1.x served its own (`Panneau::router()->auth()`, at `{prefix}/login`). 5.x's own login isn't built yet: set `auth.login_url` to a login page of the site.
- **Locales.** A `setLocalesResolver()` becomes `locales` in `config/panneau.php`.
- **Request macros and route names:** `$request->routeIs('panneau.*')`, and the 5.x route names.
- **Deployment:** deploy `public/build/` with the other built files (an Envoy task that copies `public/static/` copies `public/build/` too), and ignore `/public/build` and `/public/hot` in git.

## 7. Check

1. **Write a Policy for every item class** exposed as a resource: the Entity contract in Folklore projects, or the model. Nothing is reachable without one.
2. **Check the public ids.** The repository's `id()` decides them. Folklore repositories keep their current ids.
3. **Delete the backups** (`*.1x.bak`) once the project works.
4. **Run the project's tests,** and check every resource page in the browser.
