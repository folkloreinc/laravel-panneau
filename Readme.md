# Laravel Panneau

Admin panels and content management for Laravel. `folklore/laravel-panneau` is the reference backend of [Panneau](https://github.com/folkloreinc/panneau-js): it turns resource classes into a definition and serves the Panneau API that the `@panneau/*` React packages use.

## Versions

| Version | Branch | PHP | Laravel | Status |
|---|---|---|---|---|
| 5.x | `v5.x` (default) | 8.3+ | 12, 13 | Alphas ([roadmap](docs/roadmap.md), [CHANGELOG](CHANGELOG.md)) |
| 1.4 | `v1.4` | 8.0+ | 11 to 13 | Maintenance |
| 1.3 | `v1.3` | 7.4+ | 7 to 13 | Maintenance |

Panneau 5 is documented in [`docs/`](docs/README.md). To let AI clients use Panneau, run `php artisan panneau:install --agents` and see [setting up the MCP server](docs/mcp.md).

## Installing Panneau 5

```bash
composer require folklore/laravel-panneau:^5.0@alpha
php artisan panneau:install
```

The alphas of `laravel-panneau` go with the alphas of `@panneau/*`: upgrade both together. An alpha can break the previous one; its [GitHub release](https://github.com/folkloreinc/laravel-panneau/releases) says how to upgrade.

| `laravel-panneau` | `@panneau/*` |
|---|---|
| 5.0.0-alpha.30 | 5.0.0-alpha.52 or later |
| 5.0.0-alpha.29 | 5.0.0-alpha.52 or later |
| 5.0.0-alpha.28 | 5.0.0-alpha.51 or later |
| 5.0.0-alpha.27 | 5.0.0-alpha.45 or later |
| 5.0.0-alpha.26 | 5.0.0-alpha.44 or later |
| 5.0.0-alpha.25 | 5.0.0-alpha.43 or later |
| 5.0.0-alpha.24 | 5.0.0-alpha.43 or later |
| 5.0.0-alpha.23 | 5.0.0-alpha.41 or later |
| 5.0.0-alpha.22 | 5.0.0-alpha.38 or later |
| 5.0.0-alpha.21 | 5.0.0-alpha.37 or later |
| 5.0.0-alpha.20 | 5.0.0-alpha.36 or later |
| 5.0.0-alpha.19 | 5.0.0-alpha.33 or later |
| 5.0.0-alpha.18 | 5.0.0-alpha.32 or later |
| 5.0.0-alpha.17 | 5.0.0-alpha.29 or later |
| 5.0.0-alpha.16 | 5.0.0-alpha.28 or later |
| 5.0.0-alpha.15 | 5.0.0-alpha.27 or later |
| 5.0.0-alpha.14 | 5.0.0-alpha.19 or later |
| 5.0.0-alpha.13 | 5.0.0-alpha.18 or later |
| 5.0.0-alpha.12 | 5.0.0-alpha.16 or later |
| 5.0.0-alpha.11 | 5.0.0-alpha.16 or later |
| 5.0.0-alpha.10 | 5.0.0-alpha.15 or later |
| 5.0.0-alpha.9 | 5.0.0-alpha.13 or later |
| 5.0.0-alpha.8 | 5.0.0-alpha.10 or later |
| 5.0.0-alpha.7 | 5.0.0-alpha.7 |
| 5.0.0-alpha.6 | 5.0.0-alpha.7 |
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
