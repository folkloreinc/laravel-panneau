# 0014: An optional roles module on Policies and the Gate, without spatie/laravel-permission

- Status: accepted
- Date: 2026-11-03
- Issue: [#149](https://github.com/folkloreinc/laravel-panneau/issues/149)
- Follows: [panneau-js decision 0011](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0011-permissions.md) and [0046](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0046-abilities-in-the-ui.md)

## Context

Every operation goes through the Policy of the item ([0002](0002-secure-by-default.md)), and the definition and the items carry the abilities the Policies give ([panneau-js decision 0011](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0011-permissions.md)). Projects write their Policies. Those of 1.x wrote a `PanneauPolicy` and their own roles, again in each project.

The contests platform, pilot of phase 2, needs roles:

- **admins**, who can do everything;
- **managers**, who run the contests;
- **clients**, who reach their own contests only: they read their stats and exports, and edit some things (prizes) only when they are granted to them.

A client must never reach another client's contest, whatever the route: lists, items, updates, actions, checks, search.

### How others do it

- **Nova** authorizes with Laravel Policies (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, and `attach…`, `add…` for relations), hides resources from the navigation by `viewAny`, and fields, actions and tools with `canSee()`, `canRun()` and `readonly()` closures. It has no roles: projects add a package, usually on spatie/laravel-permission.
- **Filament** authorizes with Policies too, with `deleteAny`, `restoreAny`, `forceDeleteAny` and `reorder` for bulk actions, `canAccess()` on pages, and `visible()`, `hidden()` and `disabled()` on fields. Its tenancy scopes the queries of resources to the current tenant and checks that the user belongs to it. Roles come from Filament Shield, a community plugin on spatie/laravel-permission that generates a permission per resource and ability, and Policies that check them.
- **spatie/laravel-permission** keeps roles and permissions in the database (`roles`, `permissions` and three pivots), caches them, and registers a `Gate::before()` that allows an ability when the user has the permission of that name, and answers nothing otherwise, so the site's own gates and Policies still apply. Roles and permissions are created by seeders or a UI. Its teams add a global team id to every check.

All three keep Policies as the place where the backend decides, and the roles as data that Policies read. That's what [0002](0002-secure-by-default.md) already requires here.

## Decision

### Opt-in

- The module is off by default (`panneau.roles.enabled`). Projects with their own Policies, or their own roles (Niche, a project on spatie), don't turn it on, and nothing changes for them.
- On, it adds: roles from config, the roles and grants of each user, a `Gate::before()` for the permissions of Panneau, a base Policy that resources use, and the ownership of items. Resources keep their own Policies where they have one.

### Roles are code, users get them

- **Roles are defined in config,** with their permissions, and deployed with the resources they talk about. They are reviewed in pull requests, the same in every environment, and need no seeder, cache or UI to define them.

    ```php
    'roles' => [
        'enabled' => true,
        'roles' => [
            'admin' => ['label' => 'Admin', 'permissions' => ['*']],
            'manager' => ['label' => 'Manager', 'permissions' => ['contests.*', 'prizes.*', 'stats.view']],
            'client' => [
                'label' => 'Client',
                'permissions' => ['contests.viewAny', 'contests.view', 'contests.export', 'prizes.viewAny', 'prizes.view', 'stats.view'],
                'owned' => true,
            ],
        ],
        // Permissions given to a user on top of their roles
        'grantable' => ['prizes.create', 'prizes.update', 'prizes.delete'],
        // Other permissions the Gate answers, for pages and fields
        'permissions' => ['stats.view', 'contests.budget.update'],
    ],
    ```

- **Users get roles and grants,** stored by a `RoleStore`. The default one has two tables, `panneau_role_user` and `panneau_permission_user`, with a morph to the user, so any user model works. A grant is a permission of `grantable` given to one user: "edit prizes when granted".
- **Assignment** is a `Roles` field and a `Grants` field on the project's resource of users, which only users with the `roles.assign` permission can change, and `panneau:role {user} {role}` for the first admin. Invitations come with the auth of the app, later.

### Permissions

- **`{resource}.{ability}`**: `contests.viewAny`, `contests.view`, `contests.create`, `contests.update`, `contests.delete`, and the id of an action (`contests.export`). `contests.*` is every ability of a resource, `*` every permission.
- **Other names** listed in `permissions` are for pages, fields and the project's own checks (`stats.view`, `contests.budget.update`).
- **The Gate answers them.** A `Gate::before()` allows a permission the user has, through a role or a grant, and answers nothing otherwise, as spatie's does. `$user->can('contests.export')` works anywhere in the project, and abilities that aren't permissions of Panneau (the site's own gates) are never answered by it.
- `viewPanneau`, when the project doesn't define it, allows users with a role.

### Policies

- **`ResourcePolicy`** is the base of the Policy of a resource that uses roles. It names its resource, and checks its permissions: `viewAny`, `view`, `create`, `update`, `delete`, `deleteAny` (for the built-in `delete` on selections), and the resource's actions through `runAction()`. `panneau:install` generates such Policies when the module is on.

    ```php
    class ContestPolicy extends ResourcePolicy
    {
        protected string $resource = 'contests';
    }
    ```

- The definition's abilities and the API both come from these Policies, through the `Authorizer`, so they can't disagree: a button the user sees doesn't fail with a `403`.
- **Two additions to the `Authorizer`, for every Policy:** a Policy with `deleteAny()` declares the built-in `delete` with `"abilities": { "run": false }` when it refuses (panneau-js decision 0046), and a Policy with `runAction()` answers every action of its resource, on items and on the resource.

### Ownership

- **A role can be `owned`:** a user whose roles are all owned reaches only the items they own, in every resource. A user with another role too (an admin who is also a client) isn't limited. Grants follow the user's roles.
- **A resource says what a user owns** by implementing `OwnedResource::ownerScope($user)`: a scope, as `scope()` returns, such as `['client_id' => $user->client_id]`, or `['contest_id' => [...]]` for the prizes of the client's contests.
- **It joins the scope of every operation** ([0002](0002-secure-by-default.md)): lists, items, create (the scope is filled in), update, delete, duplicate, actions, field checks, search and relations. An item outside it is a `404`, on every route that takes an id. `ResourcePolicy` checks it again on the item (defense in depth, and for the project's own uses of the Policy).
- **Closed by default:** an owned user is refused on a resource that doesn't implement `OwnedResource`. A resource that owned users see in full (a list of countries) returns `[]`.

### Pages and fields

- A field is shown by an ability, `->canSee('contests.budget.view')`, and edited by another, `->canUpdate('contests.budget.update')`, or a closure. A page will take `->can('stats.view')` once pages are in the definition ([#33](https://github.com/folkloreinc/laravel-panneau/issues/33)). They give the `abilities` of panneau-js decision 0046, and work without the module, with any Gate ability.

### Not on spatie/laravel-permission

- **Roles in config, not in the database.** Panneau's roles are part of the admin, which is code: a role that names a resource that changed is fixed in the same pull request. spatie keeps them as data, to seed, sync and cache in each environment.
- **Ownership is a scope, not a team.** spatie's teams add one team id to every check; the contests platform needs items owned through other items (the prizes of a client's contests), which a scope says, and Panneau already applies its scope to every operation.
- **No dependency** for three roles: no cache to flush, no migrations to publish from another package.
- **Projects on spatie keep it.** They leave the module off and write their Policies with `$user->can(...)`, which spatie answers. A `RoleStore` on spatie's tables can come when a project needs one.
- **What it takes from spatie and Shield:** the `Gate::before()` that answers nothing for what it doesn't know, wildcards, permissions granted to one user, and a permission per resource and ability.

## Consequences

- Off by default: nothing changes for existing projects. On, a resource still needs its Policy (a `ResourcePolicy`), as [0002](0002-secure-by-default.md) says: roles don't open a resource without one.
- The migrations of the two tables load only when the module is on.
- The `Authorizer`'s `deleteAny()` and `runAction()` apply to every Policy that has them, with or without the module.
- Ownership adds the owner scope to `repositoryScope()`; `scope()` keeps its meaning.
