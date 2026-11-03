<?php

namespace Panneau\Auth;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Panneau\Actions\Action;
use Panneau\Actions\DeleteAction;
use Panneau\Fields\Field;
use Panneau\Http\Controllers\ResourceController;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use WeakMap;

/**
 * Authorizes every operation through Policies: the policy of the item, or of the resource's
 * `itemClass()` for `viewAny` and `create`. Without a policy, everything is denied, unless
 * `Panneau::allowWithoutPolicies()` was called.
 */
final class Authorizer
{
    /**
     * Abilities checked on each item.
     */
    public const ITEM_ABILITIES = ['view', 'update', 'delete'];

    private bool $allowWithoutPolicies = false;

    /**
     * The `create` ability of each resource, by user.
     *
     * @var WeakMap<Authenticatable, array<string, bool>>|null
     */
    private ?WeakMap $creates = null;

    /**
     * Whether each resource has a duplicate route, with the number of routes it was found with.
     *
     * @var array<string, array{int, bool}>
     */
    private array $duplicateRoutes = [];

    public function __construct(
        private readonly Gate $gate,
        private readonly ResourceRegistry $resources,
        private readonly ?Router $router = null,
    ) {}

    /**
     * Allows every operation of the resources without a policy. Meant for local development.
     */
    public function allowWithoutPolicies(bool $allow = true): void
    {
        $this->allowWithoutPolicies = $allow;
    }

    /**
     * A resource-level ability, such as `viewAny` or `create`, checked on `itemClass()`.
     */
    public function allowsResource(?Authenticatable $user, Resource $resource, string $ability): bool
    {
        $class = $resource->itemClass();
        if ($class === null || $this->gate->getPolicyFor($class) === null) {
            return $this->allowWithoutPolicies;
        }

        return $this->gate->forUser($user)->allows($ability, $class);
    }

    /**
     * An ability on an item, such as `view`, `update` or `delete`, checked on its policy.
     */
    public function allowsItem(?Authenticatable $user, Resource $resource, string $ability, object $item): bool
    {
        if (! $this->hasPolicy($resource, $item)) {
            return $this->allowWithoutPolicies;
        }

        return $this->gate->forUser($user)->allows($ability, $item);
    }

    /**
     * Whether the user can run an action on an item: the action's `authorize()`, or the
     * policy method named after the action (`publishPages`), or `update`.
     */
    public function allowsAction(?Authenticatable $user, Resource $resource, Action $action, object $item): bool
    {
        $allowed = $action->authorize($user, $item);
        if ($allowed !== null) {
            return $allowed;
        }
        if ($action instanceof DeleteAction) {
            // The ability the app shows the built-in delete by
            return $this->allowsItem($user, $resource, 'delete', $item);
        }
        if (! $this->hasPolicy($resource, $item)) {
            return $this->allowWithoutPolicies;
        }
        $policy = $this->gate->getPolicyFor($item) ?? $this->gate->getPolicyFor((string) $resource->itemClass());
        // A policy with `runAction($user, ?object $item, string $action)` answers every action of
        // its resource
        if (is_object($policy) && method_exists($policy, 'runAction')) {
            return $this->gate->forUser($user)->allows('runAction', [$item, $action->id()]);
        }
        $method = Str::camel($action->id());

        return $this->gate->forUser($user)->allows(
            is_object($policy) && method_exists($policy, $method) ? $method : 'update',
            $item,
        );
    }

    /**
     * Whether the user can delete items of the resource at all: the policy's `deleteAny()`, when
     * it has one. When it refuses, the definition declares the built-in `delete` with
     * `"abilities": { "run": false }`, so lists offer no "Delete" and no checkboxes for it
     * (panneau-js decision 0042), and its route answers `403`.
     */
    public function allowsDeleteAny(?Authenticatable $user, Resource $resource): bool
    {
        $class = $resource->itemClass();
        $policy = $class !== null ? $this->gate->getPolicyFor($class) : null;
        if (! is_object($policy) || ! method_exists($policy, 'deleteAny')) {
            return true;
        }

        return $this->gate->forUser($user)->allows('deleteAny', $class);
    }

    /**
     * Whether the user can see a field: its `canSee()` ability, or everyone.
     */
    public function canSeeField(?Authenticatable $user, Field $field): bool
    {
        return $this->allowsFieldAbility($user, $field->seeAbility());
    }

    /**
     * Whether the user can edit a field: they can see it, and have its `canUpdate()` ability.
     */
    public function canUpdateField(?Authenticatable $user, Field $field): bool
    {
        return $this->canSeeField($user, $field) && $this->allowsFieldAbility($user, $field->updateAbility());
    }

    /**
     * The abilities of a field in the definition: only those that are false (panneau-js decision
     * 0042), `view` and `update`.
     *
     * @return array<string, false>
     */
    public function fieldAbilities(?Authenticatable $user, Field $field): array
    {
        if (! $this->canSeeField($user, $field)) {
            return ['view' => false];
        }

        return $this->canUpdateField($user, $field) ? [] : ['update' => false];
    }

    /**
     * The names of the fields of the resource the user can't see: items leave their values out.
     *
     * @return list<string>
     */
    public function hiddenFields(?Authenticatable $user, Resource $resource): array
    {
        $hidden = [];
        foreach ($resource->allFields() as $field) {
            if (! $this->canSeeField($user, $field)) {
                $hidden[] = $field->name();
            }
        }

        return $hidden;
    }

    /**
     * The fields of the resource whose values the user can save: those they can see and edit.
     * Saving ignores the values of the others, as it ignores read-only fields.
     *
     * @return list<Field>
     */
    public function writableFields(?Authenticatable $user, Resource $resource): array
    {
        return array_values(array_filter(
            $resource->allFields(),
            fn (Field $field): bool => $this->canUpdateField($user, $field),
        ));
    }

    /**
     * @param  string|(Closure(?Authenticatable): bool)|null  $ability
     */
    private function allowsFieldAbility(?Authenticatable $user, string|Closure|null $ability): bool
    {
        if ($ability === null) {
            return true;
        }
        if ($ability instanceof Closure) {
            return $ability($user) === true;
        }

        return $this->gate->forUser($user)->allows($ability);
    }

    /**
     * Whether the user can duplicate an item: never when the repository can't, nor when the
     * user can't view the item, since the copy is answered in full. Then the policy's
     * `duplicate` method, or else `create` on the resource.
     */
    public function allowsDuplicate(?Authenticatable $user, Resource $resource, object $item): bool
    {
        return $this->allowsDuplicateWhenViewed($user, $resource, $item, $this->allowsItem($user, $resource, 'view', $item));
    }

    /**
     * `allowsDuplicate()`, with the `view` ability already checked. False when the app has no
     * route to duplicate the resource's items (`resourceRoutes(only: [...])` without
     * `duplicate`).
     */
    private function allowsDuplicateWhenViewed(?Authenticatable $user, Resource $resource, object $item, bool $canView): bool
    {
        if (! $canView || ! $this->resources->duplicable($resource) || ! $this->duplicateRouted($resource)) {
            return false;
        }
        if (! $this->hasPolicy($resource, $item)) {
            return $this->allowWithoutPolicies;
        }
        $policy = $this->gate->getPolicyFor($item) ?? $this->gate->getPolicyFor((string) $resource->itemClass());

        return is_object($policy) && method_exists($policy, 'duplicate')
            ? $this->gate->forUser($user)->allows('duplicate', $item)
            : $this->allowsCreate($user, $resource);
    }

    /**
     * `create` on the resource, once per user: the fallback of `duplicate` on each item.
     */
    private function allowsCreate(?Authenticatable $user, Resource $resource): bool
    {
        if ($user === null) {
            return $this->allowsResource($user, $resource, 'create');
        }
        $this->creates ??= new WeakMap;
        $allowed = $this->creates[$user] ?? [];
        if (! isset($allowed[$resource->id()])) {
            $allowed[$resource->id()] = $this->allowsResource($user, $resource, 'create');
            $this->creates[$user] = $allowed;
        }

        return $allowed[$resource->id()];
    }

    /**
     * Whether the user can upload files directly to the disk: the `uploadPanneauFiles` gate.
     * Without it, uploads are denied, unless `Panneau::allowWithoutPolicies()` was called.
     */
    public function allowsUploads(?Authenticatable $user): bool
    {
        if (! $this->gate->has('uploadPanneauFiles')) {
            return $this->allowWithoutPolicies;
        }

        return $user !== null && $this->gate->forUser($user)->allows('uploadPanneauFiles');
    }

    /**
     * Whether the action is offered to the user at all: its `authorizeResource()`, and with the
     * resource, the `deleteAny()` of its policy for the built-in `delete`, or the `runAction()`
     * of its policy, which receives `null` for the item.
     */
    public function allowsActionOnResource(?Authenticatable $user, Action $action, ?Resource $resource = null): bool
    {
        if (! $action->authorizeResource($user)) {
            return false;
        }
        if ($resource === null) {
            return true;
        }
        if ($action instanceof DeleteAction) {
            return $this->allowsDeleteAny($user, $resource);
        }
        $class = $resource->itemClass();
        $policy = $class !== null ? $this->gate->getPolicyFor($class) : null;
        if (is_object($policy) && method_exists($policy, 'runAction')) {
            // The Gate finds the policy by the class, and passes it the rest: no item, the action
            return $this->gate->forUser($user)->allows('runAction', [$class, null, $action->id()]);
        }

        return true;
    }

    /**
     * The abilities of the resource in the definition: `viewAny` and `create`.
     *
     * @return array<string, bool>
     */
    public function resourceAbilities(?Authenticatable $user, Resource $resource): array
    {
        return [
            'viewAny' => $this->allowsResource($user, $resource, 'viewAny'),
            'create' => $this->allowsResource($user, $resource, 'create'),
        ];
    }

    /**
     * The abilities of an item: `view`, `update`, `delete`, `duplicate`, and the ids of the
     * server actions on items or selections that the user can run on it. The app shows the
     * built-in actions of an item by its abilities.
     *
     * @return array<string, bool>
     */
    public function itemAbilities(?Authenticatable $user, Resource $resource, object $item): array
    {
        $abilities = [];
        foreach (self::ITEM_ABILITIES as $ability) {
            $abilities[$ability] = $this->allowsItem($user, $resource, $ability, $item);
        }
        $abilities['duplicate'] = $this->allowsDuplicateWhenViewed($user, $resource, $item, $abilities['view']);
        foreach ($this->resources->actions($resource) as $action) {
            if ($action instanceof Action && $action->appliesToItems()) {
                $abilities[$action->id()] = $this->allowsActionOnResource($user, $action, $resource)
                    && $this->allowsAction($user, $resource, $action, $item);
            }
        }

        return $abilities;
    }

    /**
     * Whether a route duplicates the items of the resource, once per state of the routes. Without
     * a router (outside HTTP), it is assumed.
     */
    private function duplicateRouted(Resource $resource): bool
    {
        if ($this->router === null) {
            return true;
        }
        $routes = $this->router->getRoutes()->getRoutes();
        [$count, $routed] = $this->duplicateRoutes[$resource->id()] ?? [-1, false];
        if ($count === count($routes)) {
            return $routed;
        }
        $routed = false;
        foreach ($routes as $route) {
            $pattern = $route->wheres['resource'] ?? null;
            if ($route->getActionName() === ResourceController::class.'@duplicate'
                && ($pattern === null || preg_match('#^(?:'.$pattern.')$#', $resource->id()) === 1)) {
                $routed = true;

                break;
            }
        }
        $this->duplicateRoutes[$resource->id()] = [count($routes), $routed];

        return $routed;
    }

    private function hasPolicy(Resource $resource, object $item): bool
    {
        $class = $resource->itemClass();

        return $this->gate->getPolicyFor($item) !== null
            || ($class !== null && $this->gate->getPolicyFor($class) !== null);
    }
}
