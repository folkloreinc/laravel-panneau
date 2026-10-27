# Laravel Panneau

Admin panels and content management for Laravel. `folklore/laravel-panneau` is the reference backend of [Panneau](https://github.com/folkloreinc/panneau-js): it turns resource classes into a definition and serves the Panneau API that the `@panneau/*` React packages use.

## Versions

| Version | Branch | PHP | Laravel | Status |
|---|---|---|---|---|
| 5.x | `v5.x` (default) | 8.3+ | 12, 13 | Alphas ([roadmap](docs/roadmap.md), [CHANGELOG](CHANGELOG.md)) |
| 1.4 | `v1.4` | 8.0+ | 11 to 13 | Maintenance |
| 1.3 | `v1.3` | 7.4+ | 7 to 13 | Maintenance |

Panneau 5 is documented in [`docs/`](docs/README.md).

## Installing Panneau 5

```bash
composer require folklore/laravel-panneau:^5.0@alpha
php artisan panneau:install
```

The alphas of `laravel-panneau` go with the alphas of `@panneau/*`: upgrade both together. An alpha can break the previous one; its [GitHub release](https://github.com/folkloreinc/laravel-panneau/releases) says how to upgrade.

| `laravel-panneau` | `@panneau/*` |
|---|---|
| 5.0.0-alpha.5 | 5.0.0-alpha.6 |
| 5.0.0-alpha.4 | 5.0.0-alpha.5 |
| 5.0.0-alpha.3 | 5.0.0-alpha.4 |
| 5.0.0-alpha.2 | 5.0.0-alpha.2 |
| 5.0.0-alpha.1 | 5.0.0-alpha.1 |

## Development

```bash
composer install
composer test      # PHPUnit
composer lint      # Pint (check)
composer format    # Pint (fix)
composer analyse   # Larastan
```

See [`AGENTS.md`](AGENTS.md) for the rules of the repository.

## License

MIT
