<?php

namespace Panneau\Auth;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Panneau\Actions\Action;
use Panneau\Actions\ClientAction;
use Panneau\Actions\DeleteAction;
use Panneau\Actions\RestoreAction;
use Panneau\Fields\Field;
use Panneau\Http\Controllers\ResourceController;
use Panneau\Resources\FieldCache;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use WeakMap;

/**
 * Authorizes every operation through Policies: the policy of the item, or of the resource's
 * `itemClass()` for `viewAny` and `create`. Without a policy, everything is denied, unless
 * `Panneau::allowWithoutPolicies()` was called.
 *
 * What only depends on the user and the resource (resource abilities, the actions offered, the
 * hidden fields, the policy of a class) is checked once per request (#244), in the request's
 * `FieldCache`: it is flushed when a request is handled, a user is authenticated or the context
 * is resolved. On each item, an ability is checked once through the Gate.
 */
final class Authorizer
{
    /**
     * Abilities checked on each item.
     */
    public const ITEM_ABILITIES = ['view', 'update', 'delete'];

    private bool $allowWithoutPolicies = false;

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
        private readonly ?Container $container = null,
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
        return $this->remember($user, $resource, 'resource:'.$ability, function () use ($user, $resource, $ability): bool {
            $class = $resource->itemClass();
            if ($class === null || $this->policyFor($class) === null) {
                return $this->allowWithoutPolicies;
            }

            return $this->gate->forUser($user)->allows($ability, $class);
        });
    }

    /**
     * An ability on an item, such as `view`, `update` or `delete`, checked on its policy.
     */
    public function allowsItem(?Authenticatable $user, Resource $resource, string $ability, object $item): bool
    {
        return $this->checkItem($resource, $item, $ability, $this->itemChecks($user, $item));
    }

    /**
     * @param  Closure(string, ?string=): bool  $allows
     */
    private function checkItem(Resource $resource, object $item, string $ability, Closure $allows): bool
    {
        if (! $this->hasPolicy($resource, $item)) {
            return $this->allowWithoutPolicies;
        }

        return $allows($ability);
    }

    /**
     * Whether the user can run an action on an item: the action's `authorize()`, or the
     * policy method named after the action (`publishPages`), or `update`. A client action has no
     * `authorize()`: the policy answers for it.
     */
    public function allowsAction(?Authenticatable $user, Resource $resource, Action|ClientAction $action, object $item): bool
    {
        return $this->checkAction($user, $resource, $action, $item, $this->itemChecks($user, $item));
    }

    /**
     * @param  Closure(string, ?string=): bool  $allows
     */
    private function checkAction(?Authenticatable $user, Resource $resource, Action|ClientAction $action, object $item, Closure $allows): bool
    {
        $allowed = $action instanceof Action ? $action->authorize($user, $item) : null;
        if ($allowed !== null) {
            return $allowed;
        }
        if ($action instanceof DeleteAction) {
            // The ability the app shows the built-in delete by
            return $this->checkItem($resource, $item, 'delete', $allows);
        }
        if ($action instanceof RestoreAction) {
            // The undo of delete: the policy's `restore`, or whoever could delete the item
            $policy = $this->hasPolicy($resource, $item) ? $this->policyFor($item::class) : null;

            return $this->checkItem($resource, $item, $policy !== null && method_exists($policy, 'restore') ? 'restore' : 'delete', $allows);
        }
        $policy = $this->itemPolicy($resource, $item);
        if ($policy === null) {
            return $this->allowWithoutPolicies;
        }
        // A policy with `runAction($user, ?object $item, string $action)` answers every action of
        // its resource
        if (method_exists($policy, 'runAction')) {
            return $allows('runAction', $action->id());
        }
        $method = Str::camel($action->id());

        return $allows(method_exists($policy, $method) ? $method : 'update');
    }

    /**
     * Whether the user can delete items of the resource at all: the policy's `deleteAny()`, when
     * it has one. When it refuses, the definition declares the built-in `delete` with
     * `"abilities": { "run": false }`, so lists offer no "Delete" and no checkboxes for it
     * (panneau-js decision 0046), and its route answers `403`.
     */
    public function allowsDeleteAny(?Authenticatable $user, Resource $resource): bool
    {
        return $this->remember($user, $resource, 'deleteAny', function () use ($user, $resource): bool {
            $class = $resource->itemClass();
            $policy = $class !== null ? $this->policyFor($class) : null;
            if ($policy === null || ! method_exists($policy, 'deleteAny')) {
                return true;
            }

            return $this->gate->forUser($user)->allows('deleteAny', $class);
        });
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
     * 0046), `view` and `update`.
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
     * In a resource with types, those of the items of a type.
     *
     * @return list<string>
     */
    public function hiddenFields(?Authenticatable $user, Resource $resource, ?string $type = null): array
    {
        return $this->remember($user, $resource, 'hiddenFields:'.($type ?? ''), function () use ($user, $resource, $type): array {
            $hidden = [];
            foreach ($resource->typeFields($type) as $field) {
                if (! $this->canSeeField($user, $field)) {
                    $hidden[] = $field->name();
                }
            }

            return $hidden;
        });
    }

    /**
     * The fields of the resource whose values the user can save: those they can see and edit.
     * Saving ignores the values of the others, as it ignores read-only fields. In a resource with
     * types, those of the items of a type.
     *
     * @return list<Field>
     */
    public function writableFields(?Authenticatable $user, Resource $resource, ?string $type = null): array
    {
        return array_values(array_filter(
            $resource->typeFields($type),
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
        $allows = $this->itemChecks($user, $item);

        return $this->allowsDuplicateWhenViewed($user, $resource, $item, $this->checkItem($resource, $item, 'view', $allows), $allows);
    }

    /**
     * `allowsDuplicate()`, with the `view` ability already checked. False when the app has no
     * route to duplicate the resource's items (`resourceRoutes(only: [...])` without
     * `duplicate`), and for an item of a type the user can't create, such as a singleton.
     *
     * @param  Closure(string, ?string=): bool  $allows
     */
    private function allowsDuplicateWhenViewed(?Authenticatable $user, Resource $resource, object $item, bool $canView, Closure $allows): bool
    {
        if (! $canView || ! $this->resources->duplicable($resource) || ! $this->duplicateRouted($resource)) {
            return false;
        }
        $type = $resource->typeOf($item);
        if ($type !== null && ! $this->remember($user, $resource, 'createType:'.$type, fn (): bool => $resource->resourceType($type)?->canCreate($user) ?? true)) {
            return false;
        }
        $policy = $this->itemPolicy($resource, $item);
        if ($policy === null) {
            return $this->allowWithoutPolicies;
        }

        // `create` on the resource is checked once per request
        return method_exists($policy, 'duplicate')
            ? $allows('duplicate')
            : $this->allowsResource($user, $resource, 'create');
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
     * of its policy, which receives `null` for the item. A client action has no
     * `authorizeResource()`: only the policy's `runAction()` can refuse it.
     */
    public function allowsActionOnResource(?Authenticatable $user, Action|ClientAction $action, ?Resource $resource = null): bool
    {
        if ($resource === null) {
            return ! $action instanceof Action || $action->authorizeResource($user);
        }

        return $this->remember($user, $resource, 'action:'.$action->id(), function () use ($user, $action, $resource): bool {
            if ($action instanceof Action && ! $action->authorizeResource($user)) {
                return false;
            }
            if ($action instanceof DeleteAction) {
                return $this->allowsDeleteAny($user, $resource);
            }
            $class = $resource->itemClass();
            $policy = $class !== null ? $this->policyFor($class) : null;
            if ($policy !== null && method_exists($policy, 'runAction')) {
                // The Gate finds the policy by the class, and passes it the rest: no item, the action
                return $this->gate->forUser($user)->allows('runAction', [$class, null, $action->id()]);
            }

            return true;
        });
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
     * server and client actions on items or selections that the user can run on it. The app
     * shows the built-in actions of an item, and its server and client actions, by its abilities.
     *
     * @return array<string, bool>
     */
    public function itemAbilities(?Authenticatable $user, Resource $resource, object $item): array
    {
        $allows = $this->itemChecks($user, $item);
        $abilities = [];
        foreach (self::ITEM_ABILITIES as $ability) {
            $abilities[$ability] = $this->checkItem($resource, $item, $ability, $allows);
        }
        $abilities['duplicate'] = $this->allowsDuplicateWhenViewed($user, $resource, $item, $abilities['view'], $allows);
        foreach ($this->resources->actions($resource) as $action) {
            if (($action instanceof Action || $action instanceof ClientAction) && $action->appliesToItems()) {
                $abilities[$action->id()] = $this->allowsActionOnResource($user, $action, $resource)
                    && $this->checkAction($user, $resource, $action, $item, $allows);
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
        return $this->itemPolicy($resource, $item) !== null;
    }

    /**
     * The policy of an item, or else of the resource's `itemClass()`.
     */
    private function itemPolicy(Resource $resource, object $item): ?object
    {
        $class = $resource->itemClass();

        return $this->policyFor($item::class) ?? ($class !== null ? $this->policyFor($class) : null);
    }

    /**
     * The policy of a class, resolved once per request to read its methods: the Gate resolves it
     * again for each check.
     */
    private function policyFor(string $class): ?object
    {
        return $this->cache()->remember($this, 'authorizer.policy:'.$class, function () use ($class): ?object {
            $policy = $this->gate->getPolicyFor($class);

            return is_object($policy) ? $policy : null;
        });
    }

    /**
     * Checks abilities on an item through the Gate, each once: an ability, with the id of an
     * action for `runAction`.
     *
     * @return Closure(string, ?string=): bool
     */
    private function itemChecks(?Authenticatable $user, object $item): Closure
    {
        $gate = $this->gate->forUser($user);
        $checked = [];

        return function (string $ability, ?string $action = null) use ($gate, $item, &$checked): bool {
            return $checked[$ability.'|'.($action ?? '')] ??= $gate->allows($ability, $action === null ? $item : [$item, $action]);
        };
    }

    /**
     * A result that depends on the user and the resource, built once per request. Kept with the
     * resource, so that `flushFields()` forgets it too, by user.
     *
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    private function remember(?Authenticatable $user, Resource $resource, string $key, Closure $build): mixed
    {
        $key = 'authorizer.'.$key;
        if ($user === null) {
            return $this->cache()->remember($resource, $key.'|guest', $build);
        }
        /** @var WeakMap<Authenticatable, array<string, mixed>> $users */
        $users = $this->cache()->remember($resource, 'authorizer.users', fn (): WeakMap => new WeakMap);
        if (isset($users[$user]) && array_key_exists($key, $users[$user])) {
            /** @var T */
            return $users[$user][$key];
        }
        $value = $build();
        // Read again: building can remember other results of the same user
        $users[$user] = [...$users[$user] ?? [], $key => $value];

        return $value;
    }

    private function cache(): FieldCache
    {
        return ($this->container ?? \Illuminate\Container\Container::getInstance())->make(FieldCache::class);
    }
}
