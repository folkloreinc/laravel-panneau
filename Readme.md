# Laravel Panneau


[![Latest Stable Version](https://poser.pugx.org/folklore/laravel-panneau/v/stable.svg)](https://packagist.org/packages/folklore/laravel-panneau)
[![Build Status](https://travis-ci.org/Folkloreatelier/laravel-panneau.png?branch=v1-rc)](https://travis-ci.org/Folkloreatelier/laravel-panneau)
[![Coverage Status](https://coveralls.io/repos/Folkloreatelier/laravel-panneau/badge.svg?branch=v1-rc&service=github)](https://coveralls.io/github/Folkloreatelier/laravel-panneau?branch=v1-rc)
[![Total Downloads](https://poser.pugx.org/folklore/laravel-panneau/downloads.svg)](https://packagist.org/packages/folklore/laravel-panneau)

## Installation

```shell
composer require folklore/laravel-panneau:dev-v1-rc
```

### Laravel 5.4 and lower

**1-** Add the service provider to your `app/config/app.php` file

```php
Folklore\Panneau\PanneauerviceProvider::class,
```

**2-** Add the facade to your `app/config/app.php` file

```php
'Panneau' => Folklore\Panneau\Facades\Panneau::class,
```

### All versions

**1-** Publish the configuration file and public files

```bash
$ php artisan vendor:publish
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
