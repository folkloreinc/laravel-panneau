<?php

namespace Panneau\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Panneau\Actions\Action;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

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

    public function __construct(
        private readonly Gate $gate,
        private readonly ResourceRegistry $resources,
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
        if (! $this->hasPolicy($resource, $item)) {
            return $this->allowWithoutPolicies;
        }
        $policy = $this->gate->getPolicyFor($item) ?? $this->gate->getPolicyFor((string) $resource->itemClass());
        $method = Str::camel($action->id());

        return $this->gate->forUser($user)->allows(
            is_object($policy) && method_exists($policy, $method) ? $method : 'update',
            $item,
        );
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
     * Whether the action is offered to the user at all.
     */
    public function allowsActionOnResource(?Authenticatable $user, Action $action): bool
    {
        return $action->authorizeResource($user);
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
     * The abilities of an item: `view`, `update`, `delete`, and the ids of the server actions
     * on items or selections that the user can run on it.
     *
     * @return array<string, bool>
     */
    public function itemAbilities(?Authenticatable $user, Resource $resource, object $item): array
    {
        $abilities = [];
        foreach (self::ITEM_ABILITIES as $ability) {
            $abilities[$ability] = $this->allowsItem($user, $resource, $ability, $item);
        }
        foreach ($this->resources->actions($resource) as $action) {
            if ($action instanceof Action && $action->appliesToItems()) {
                $abilities[$action->id()] = $this->allowsActionOnResource($user, $action)
                    && $this->allowsAction($user, $resource, $action, $item);
            }
        }

        return $abilities;
    }

    private function hasPolicy(Resource $resource, object $item): bool
    {
        $class = $resource->itemClass();

        return $this->gate->getPolicyFor($item) !== null
            || ($class !== null && $this->gate->getPolicyFor($class) !== null);
    }
}
