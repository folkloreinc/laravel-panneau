# Migration (Laravel)

The migration strategy, order and v1 → 5 mapping are in [`panneau-js/docs/migration.md`](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/migration.md). This page adds the Laravel steps.

1. **Upgrade the project's versions first.** It needs PHP 8.3+ and Laravel 12+ (see [decisions/0006](decisions/0006-supported-versions.md)).
2. **Require the new version.** Require `folklore/laravel-panneau:^5.0`, and `folklore/laravel-folklore` at the version that ships the Panneau 5 plugin, if the project uses it.
3. **Run `php artisan panneau:install --upgrade`.** It converts `config/panneau.php`, creates the service provider and routes file, and lists the generated v1 files that the package now provides.
4. **Run the Rector rules.** They convert static properties to methods, rename field classes and turn `*Localized` fields into `->localized()`.
5. **Keep entities and repositories.** Folklore projects point each resource to its repository contract (`repository()`), and the `laravel-folklore` plugin adapts it. Static `$repository` and `$jsonResource` become `repository()` and `toItem()` or `jsonResource()`.
6. **Move the rest by hand:**
   - custom controllers to hooks and `Action` classes: exports and imports to `ExportAction` and `ImportAction`, other operations on items to custom actions;
   - `routes/panneau.php` custom routes to actions or plugin pages;
   - `JsonResource` classes to `toItem()`, where it simplifies.
7. **Write a Policy for every item class** exposed as a resource: the Entity contract in Folklore projects, or the model. Nothing is reachable without one.
8. **Check the public ids.** The repository's `id()` decides them. Folklore repositories keep their current ids.
9. **Run the project's tests,** and check every resource page in the browser.
