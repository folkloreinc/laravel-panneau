<?php

namespace Panneau\Http\Controllers\Concerns;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Panneau\Forms\FormMode;
use Panneau\Forms\ValidationContext;
use Panneau\Resources\Resource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
trait ResolvesResources
{
    protected function findResource(string $id): Resource
    {
        return $this->resources->find($id) ?? throw new NotFoundHttpException(sprintf('No resource "%s".', $id));
    }

    protected function findItem(Resource $resource, string $id, Request $request): object
    {
        return $this->resources->repository($resource)->find($id, $resource->scope($request))
            ?? throw new NotFoundHttpException;
    }

    protected function authorizeResource(Request $request, Resource $resource, string $ability): void
    {
        if (! $this->authorizer->allowsResource($request->user(), $resource, $ability)) {
            throw new AuthorizationException;
        }
    }

    protected function authorizeItem(Request $request, Resource $resource, string $ability, object $item): void
    {
        if (! $this->authorizer->allowsItem($request->user(), $resource, $ability, $item)) {
            throw new AuthorizationException;
        }
    }

    protected function validationContext(Request $request, FormMode $mode, bool $partial = false, ?object $item = null): ValidationContext
    {
        return new ValidationContext(
            mode: $mode,
            locales: $this->definition->locales(),
            partial: $partial,
            item: $item,
            relationExists: $this->relationExists($request),
        );
    }

    /**
     * Whether an id exists in a resource, within its scope.
     *
     * @return Closure(string, string): bool
     */
    private function relationExists(Request $request): Closure
    {
        return function (string $resource, string $id) use ($request): bool {
            $related = $this->resources->resolve($resource);

            return $related !== null
                && $this->resources->repository($related)->find($id, $related->scope($request)) !== null;
        };
    }
}
