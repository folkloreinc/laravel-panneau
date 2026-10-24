# Laravel Panneau

[![Latest Stable Version](https://poser.pugx.org/folklore/laravel-panneau/v/stable.svg)](https://packagist.org/packages/folklore/laravel-panneau)
[![Total Downloads](https://poser.pugx.org/folklore/laravel-panneau/downloads.svg)](https://packagist.org/packages/folklore/laravel-panneau)

> **This is the `v1.4` branch**, maintained for fixes and security only, with PHP 8.0+ and Laravel 11 to 13. Panneau 5, a rewrite on a written protocol with `@panneau/*` 5, is developed on [`v5.x`](https://github.com/folkloreinc/laravel-panneau/tree/v5.x), the default branch, and released as alphas. Projects on 1.x move to it after 5.0, with its migration guide. The branches `v0`, `v1` and `v1.2` get no fixes, not even security ones.

## Security

### Protect the routes

The Panneau routes have no authentication by default: the `routes.middleware` config only contains `DispatchHandlingRequestEvent`. Register the resources routes behind your own middleware in `routes/panneau.php`:

```php
use Panneau\Support\Facade as Panneau;

Panneau::router()->group(function () {
    Route::middleware(['web'])->group(function () {
        Panneau::router()->auth();
    });

    Route::middleware(['web', 'auth', 'can:view,' . \Panneau\Panneau::class])->group(function () {
        Panneau::router()->resources();
    });
});
```

Or add `web` and `auth` to `routes.middleware` in `config/panneau.php`.

### Authorize actions per resource

Once a user passes the routes middleware, every action is allowed on every resource. Override `authorize()` in a resource to restrict them. `ResourceController` returns a 403 when it returns `false`. The abilities are `viewAny`, `view`, `create`, `update`, `delete` and `clone`. `$item` is set for the actions on an item.

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Panneau\Contracts\ResourceItem;

class Pages extends \Panneau\Support\Resource
{
    public function authorize(string $ability, Request $request, ?ResourceItem $item = null): bool
    {
        return Gate::allows('pages.' . $ability, $item);
    }
}
```

## Installation

Require a tagged release, not the branch:

```shell
composer require folklore/laravel-panneau:~1.4.0
```

**1-** Publish the configuration file and public files

```bash
php artisan vendor:publish
```

**2-** Add this to `app/Providers/RouteServiceProvider.php`

```php
/**
 * Define the routes for the application.
 *
 * @return void
 */
public function map()
{
    $this->mapApiRoutes();

    $this->mapWebRoutes();

    app('panneau')->routes();
}
```

**3-** Review the following files:
- Configuration: `config/panneau.php`
- Routes: `routes/panneau.php`
- Views: `resources/views/vendor/panneau/`
- Assets: `resources/assets/vendor/panneau/`

## Saving only declared fields

By default, `ResourceController` passes every key of a store or update request to the repository, including keys that don't match a field of the resource. Enable `only_declared_fields` to pass only the data of declared fields (the resource's fields, its types' fields, its forms' fields and `type`):

```php
// config/panneau.php
'only_declared_fields' => true,
```

Or per resource:

```php
class Pages extends \Panneau\Support\Resource
{
    public static $onlyDeclaredFields = true;
}
```

Check that your repositories don't rely on keys that aren't declared as fields before enabling it: those keys are no longer passed to `create()` and `update()`.
