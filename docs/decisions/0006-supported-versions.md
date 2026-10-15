# 0006: PHP 8.3+ and Laravel 12+

- Status: accepted
- Date: 2026-10-14

## Context

The workspace rule: a new line can require more than the stack floor (PHP 8.2, Laravel 9) when all the active projects that will use it are more recent.

The projects planned for migration to 5.x run:

| Group | Laravel | PHP |
|---|---|---|
| Niche | 13 | 8.5 |
| Projects on v1.4 + v4 | 12 or 13 | 8.3 or 8.4 |
| Projects on v1.3 + v4 | 12 or 13 | 8.3 to 8.5 |

Projects on older Laravel versions stay on v1.3 until they are upgraded.

Laravel 12 gets security fixes until February 2027. PHP 8.3 gets them until the end of 2027.

## Decision

- `laravel-panneau` 5.x requires **PHP 8.3+** and **Laravel 12+**.
- CI tests PHP 8.3 to 8.5 against Laravel 12 and 13.
- Dropping Laravel 12 follows the workspace rule: in a new minor, once no active 5.x project uses it.

## Consequences

- The code can use PHP 8.3 features: typed class constants, readonly properties and classes, enums.
- Checked against the projects inventory on 2026-10-14: every active project on `laravel-panneau` v1.3 or v1.4 with `panneau-js` v4 runs Laravel 12 or 13, on PHP 8.3 or later.
