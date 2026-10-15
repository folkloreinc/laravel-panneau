# Laravel Panneau

Admin panels and content management for Laravel. `folklore/laravel-panneau` is the reference backend of [Panneau](https://github.com/folkloreinc/panneau-js): it turns resource classes into a definition and serves the Panneau API that the `@panneau/*` React packages use.

## Versions

| Version | Branch | PHP | Laravel | Status |
|---|---|---|---|---|
| 5.x | `v5.x` | 8.3+ | 12, 13 | In development ([roadmap](https://github.com/folkloreinc/laravel-panneau/issues/35)) |
| 1.4 | `v1.4` | 8.0+ | 11 to 13 | Maintenance |
| 1.3 | `v1.3` | 7.4+ | 7 to 13 | Maintenance |

Panneau 5 is documented in [`docs/`](docs/README.md). Installation instructions will come with the first 5.0 pre-release.

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
