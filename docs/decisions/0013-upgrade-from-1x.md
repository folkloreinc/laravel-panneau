# 0013: The upgrade from 1.x reads the project without running it, and never loses a file

- Status: proposed
- Date: 2026-10-29
- Issues: [#114](https://github.com/folkloreinc/laravel-panneau/issues/114), [#115](https://github.com/folkloreinc/laravel-panneau/issues/115)

## Context

[Decision 0004](0004-install-command.md) plans `panneau:install --upgrade` for projects on 1.3 and 1.4. Those projects share a shape (`app/Panneau`, a generated provider, `routes/panneau.php`, `@panneau/*` 4 built with webpack), but each one extends it: resources that build their index at runtime, fields that extend the project's own field classes, custom routes, controllers and components.

- Once 5.x is installed, the project's 1.x classes extend classes that no longer exist. Loading one is a fatal error, and so is booting the 1.x provider, which calls them.
- What the upgrade replaces holds the project's choices (its config, its routes), and some of it can't be converted.
- The admin needs Vite, while the sites build with `@folklore/cli` (webpack), and deploy what that builds.

## Decision

- **Read, never run.** The audit parses the project's PHP with `nikic/php-parser` (a new dependency), and reads `package.json`, `Envoy.blade.php` and `.github/workflows` as text. It never loads a class of the project, never requires its config, and resolves parents through the files of the project's PSR-4 directories. What a literal doesn't say (an index built by a method, a dynamic setting) is reported as such, not guessed.
- **`--check` writes nothing.** It lists what the project has, with its file and line, and the 5.x construct each part becomes, or `manual` when the docs settle nothing: a field type whose component only the project's JS knows, a custom route, a setting 5.x doesn't have. The mappings come from the [migration guide](../migration.md); the upgrade invents no API.
- **Backups, and refusals.** A file the upgrade recognizes as 1.x is replaced, and kept as `{file}.1x.bak`; so are the files it edits (`package.json`, the Vite config, the provider list). A file it doesn't recognize is kept unless `--force`. A backup is never overwritten: when `{file}.1x.bak` has other contents, the file is kept, or with `--force` backed up as `{file}.1x.bak.2`. Once the config is 5.x, the provider isn't written again, since its resources came from the 1.x config. A 1.x override of the page is moved aside, not replaced by a copy of the package's page, which would pin it. The 1.x files the package replaces are deleted only once the user confirms, in a terminal.
- **`--check` works at every step.** It reports whatever is left of 1.x (classes on 1.x parents, request macros, route names, TODO comments), not only before the upgrade.
- **The config keeps what 5.x doesn't know.** Keys that aren't 1.x keys are kept, as written, at the end of the new config: projects read their own keys from it.
- **The 5.x provider takes the 1.x provider's line** in `config/app.php` or `bootstrap/providers.php`, even commented out, since the guide has the project comment it before installing 5.x.
- **Rector rules for the code.** The mechanical part of resources and fields (static properties, `index()`, field classes, builder methods) is rewritten by Rector rules shipped in `rector.php`, which read parents the same way. They convert only what the docs settle; elsewhere they leave a `// TODO(panneau-5)` comment, and `--check` lists those comments. Classes whose 5.x counterpart is configured differently (resource types, field classes, controllers) keep their 1.x parent, with a TODO, rather than a parent whose methods they'd clash with.
- **Vite next to the site's bundler.** A project without a Vite config gets one that builds the admin only, and scripts that `build` runs. The site keeps its bundler; the deployment files are checked for what they copy.

## Consequences

- The audit works on a project that doesn't boot, and can't run code a project didn't mean to run.
- Static analysis misses what only runs at runtime (a column added in a loop, a resource registered in code). Those parts are reported as built at runtime, and the guide has the project check every resource page.
- Running the upgrade twice changes nothing, and nothing is lost without `--force`.
