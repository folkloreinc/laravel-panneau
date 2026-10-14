# 0004: `panneau:install` replaces the generated boilerplate

- Status: accepted
- Date: 2026-10-14

## Context

The `laravel-panneau` generator of `generator-folklore` (in `folklore-js`) copies about 60 files into each project. They are nearly identical across projects and drift over time:

- `HomeController`, `AccountController`, `UploadController`, `PanneauPolicy`, `AppComposer`;
- Blade views;
- a service provider.

Fixes to these files never reach existing projects.

## Decision

- **The package provides what was generated:** controllers, views, the composer, and the upload and account endpoints.
- **`php artisan panneau:install` only writes what belongs to the project:**
  - config;
  - service provider (resources, gate);
  - routes file;
  - JS entry point and Vite input;
  - npm dependencies;
  - optionally a first resource and its policy.
- **`panneau:install --upgrade`** migrates a v1 project:
  - it converts the config;
  - it lists the files that the package now provides, and deletes them after confirmation;
  - it points to the Rector rules and JS codemods.
- **`generator-folklore` delegates.** Its `laravel-panneau` generator calls `panneau:install`.

## Consequences

- A fix in the package reaches every project through `composer update`.
- The generator stays the entry point for new Folklore projects, but no longer owns Panneau's files.
