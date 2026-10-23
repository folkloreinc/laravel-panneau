<?php

namespace Panneau\Http\Controllers\Concerns;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Panneau\Forms\FormMode;
use Panneau\Forms\ValidationContext;
use Panneau\Media\MediaReferences;
use Panneau\Resources\Resource;
use Panneau\Uploads\Upload;
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
        return $this->resources->repository($resource)->find($id, $resource->repositoryScope($request))
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

    /**
     * @param  (Closure(mixed): ?Upload)|null  $uploads  From `uploadResolver()`.
     * @param  MediaReferences|null  $media  From `mediaReferences()`.
     * @param  (Closure(string, string, ?string, ?Closure): bool)|null  $valueExists  For unique
     *                                                                                slugs, from `FieldChecks::lookup()`.
     */
    protected function validationContext(Request $request, FormMode $mode, bool $partial = false, ?object $item = null, ?Closure $uploads = null, ?MediaReferences $media = null, ?Closure $valueExists = null): ValidationContext
    {
        $media ??= $this->mediaReferences($request);

        return new ValidationContext(
            mode: $mode,
            locales: $this->definition->locales(),
            partial: $partial,
            item: $item,
            relationExists: $this->relationExists($request),
            uploads: $uploads ?? $this->uploadResolver($request),
            media: fn (?string $library, string $id): ?array => $media->media($library, $id),
            valueExists: $valueExists,
        );
    }

    /**
     * Finds the media of `media` values, within the scope of their library, once for a request:
     * validation and serialization ask for the same media.
     */
    protected function mediaReferences(Request $request): MediaReferences
    {
        return new MediaReferences($this->resources, $request);
    }

    /**
     * Resolves upload references (`{ "upload": "upl_…" }`) to completed uploads of the user,
     * once per upload for a request: validation and saving ask for the same uploads.
     *
     * @return Closure(mixed): ?Upload
     */
    protected function uploadResolver(Request $request): Closure
    {
        $user = $request->user();
        $resolved = [];

        return function (mixed $value) use ($user, &$resolved): ?Upload {
            $uid = is_array($value) ? ($value['upload'] ?? null) : null;
            if (! is_string($uid)) {
                return null;
            }
            if (! array_key_exists($uid, $resolved)) {
                $resolved[$uid] = $this->uploads->resolve($value, $user);
            }

            return $resolved[$uid];
        };
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
                && $this->resources->repository($related)->find($id, $related->repositoryScope($request)) !== null;
        };
    }
}
