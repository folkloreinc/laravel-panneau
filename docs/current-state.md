# Current state (October 2026)

Audit of `laravel-panneau` v1.4.0 (branch `v1.4`), compared with v1.3. The overall picture, including the frontend and how projects use Panneau, is in [`panneau-js/docs/current-state.md`](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/current-state.md).

## Lines

- **v1.3:** PHP 7.4+, Laravel 7 to 13. Its test tools (Testbench 5 to 7) can't test Laravel 11 and later.
- **v1.4:** PHP 8.0+, Laravel 11 to 13. Compared with v1.3, it adds:
  - the `panneau_route()` helper;
  - a concrete `Item` field and the `ResourceItems` field;
  - value preparation before validation (dates converted to the app timezone).
- **`master` and `develop`** hold 2018 code. Every Dependabot branch targets them.
- **Tags:** every project requires dev branches, never tags.

## How it works

- **Resources:**
  - Resources extend `Panneau\Support\Resource` and are configured through static properties: `$repository`, `$jsonResource`, `$types`, `$settings`…
  - They are listed in `config('panneau.resources')`.
  - Fields come from `fields()`.
- **Data:** the package is generic over a `Repository` contract and `ResourceItem` items, not Eloquent. `laravel-folklore` provides the Eloquent repository, with filters, an order allowlist and pagination.
- **Definition:** it reaches the JS app embedded in a Blade view (`#app-props`). There is no definition endpoint.
- **Routes:**
  - `Route::resource()` on a `{panneau_resource}` parameter;
  - `delete`, `duplicate` and `clone` routes;
  - login and logout through Fortify.
- **Middleware:** the default middleware is only `DispatchHandlingRequestEvent`. There is no `web` (session, CSRF) and no `auth`.
- **Fields:** 46 field classes, serialized with a `component` name that the JS resolves.

## Problems

### Security

- The default routes have no authentication, and index, show, destroy and clone check no authorization.
- Store and update requests only check that a user is logged in.
- The data saved is `$request->except(['_token'])`, not the validated data. Any attribute can be mass-assigned through the repository.

### Bugs

These are tracked as separate issues for v1.3 and v1.4:

- `ImageUpload`, `VideoUpload`, `AudioUpload` and `DocumentUpload` cause a fatal error when loaded (property type mismatch with `Media`).
- `Item::fields()` is called but doesn't exist, a regression in v1.4.
- `Field::withProperties()` and `withAttributes()` fail on a null property.
- `clone` redirects to a route that doesn't exist, while the JS expects JSON.
- The `LocalizedField` locales resolver reads a config key that doesn't exist.
- `DefinitionTest::testRoutes` fails, and the fixtures have bugs.

### Missing

- **Routes the JS calls but the package doesn't provide:**
  - upload;
  - media library;
  - `auth/check`, password reset, registration;
  - account;
  - restore.

  Every project ships its own `UploadController`, `AccountController`, `HomeController` and `PanneauPolicy`.
- **Auth:** no 2FA, no passkeys, no OAuth providers.
- **Authorization:** no permissions per resource.
- **Validation:**
  - `Email` has no `email` rule and `Select` has no `in` rule.
  - The `Date` format is a TODO.
  - Only top-level fields can be required.
- **Definition:** pages, menus and auth options can't be configured (see #33 and #34).

### Design

- Static mutable configuration everywhere: static properties on resources, static resolvers on `Upload`, `Item`, `LocalizedField` and `laravel-folklore`'s `PageSlug`.
- `laravel-folklore`'s `Entity` and `Entities` contracts extend Panneau's `ResourceItem` and `Repository`. The two packages can't evolve separately.
- Component names are inconsistent (`image_upload`, `date-time`, `resource-item`).

### Tooling

- **Tests:** 9 tests: definition, one controller test, and value preparation. One fails.
- **CI:** Travis (inactive) and Coveralls. No Pint, no Larastan.
- **Stale files:** `phpunit.xml` uses the PHPUnit 9 schema, and `.phpunit.result.cache` is committed.
- **Docs:** the README describes Laravel 5.4 and a `dev-v1-rc` branch. There is no CHANGELOG and no `AGENTS.md`.
