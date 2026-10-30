# Decisions (Laravel)

Laravel-specific decision records for Panneau 5. Shared decisions (versioning, protocol, validation, permissions, auth, migration…) are in [`panneau-js/docs/decisions`](https://github.com/folkloreinc/panneau-js/tree/v5.x/docs/decisions).

| # | Decision | Status |
|---|---|---|
| [0001](0001-reference-implementation.md) | `laravel-panneau` is the reference implementation of the protocol | Accepted |
| [0002](0002-secure-by-default.md) | Secure by default | Accepted |
| [0003](0003-resources.md) | Resources are backed by repositories, with no item contract | Accepted |
| [0004](0004-install-command.md) | `panneau:install` replaces the generated boilerplate | Accepted |
| [0005](0005-auth.md) | Auth modes and guards | Accepted |
| [0006](0006-supported-versions.md) | PHP 8.3+ and Laravel 12+ | Accepted |
| [0007](0007-direct-uploads.md) | Panneau owns direct uploads | Accepted; media items from an upload are settled by 0009 |
| [0008](0008-field-preparation.md) | Fields prepare their values before the repository saves them | Accepted |
| [0009](0009-media-libraries.md) | Media libraries are resources | Accepted; a `media` field on several libraries follows [panneau-js decision 0027](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0027-media-picker.md) ([#95](https://github.com/folkloreinc/laravel-panneau/issues/95)) |
| [0010](0010-global-search.md) | The global search goes through the repositories, and through Scout for searchable models | Accepted |
| [0011](0011-slug-checks.md) | Slugs check their value through the repository, and saving checks it again | Accepted |
| [0012](0012-embeds-and-more-field-types.md) | Embeds come from an allowlist of oEmbed providers, and saving resolves them again | Accepted |
| [0013](0013-upgrade-from-1x.md) | The upgrade from 1.x reads the project without running it, and never loses a file | Accepted |
