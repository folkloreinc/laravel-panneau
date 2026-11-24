<?php

namespace Panneau\Operations\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Panneau\Forms\FormMode;
use Panneau\Forms\ValidationContext;
use Panneau\Media\MediaReferences;
use Panneau\Resources\MediaResource;
use Panneau\Uploads\Upload;

/**
 * The context to validate values in, with the uploads of the user. The class that uses it has
 * `$resources`, `$definition` and `$uploads`, and uses `ResolvesItems`.
 *
 * @internal
 */
trait ValidatesValues
{
    /**
     * @param  Closure(mixed): ?Upload  $uploads  From `uploadResolver()`.
     * @param  MediaReferences|null  $media  From `mediaReferences()`.
     * @param  (Closure(string, string, ?string, ?Closure): bool)|null  $valueExists  For unique
     *                                                                                slugs, from `FieldChecks::lookup()`.
     */
    protected function validationContext(Request $request, FormMode $mode, Closure $uploads, bool $partial = false, ?object $item = null, ?MediaReferences $media = null, ?Closure $valueExists = null): ValidationContext
    {
        $media ??= $this->mediaReferences($request);

        return new ValidationContext(
            mode: $mode,
            locales: $this->definition->locales(),
            partial: $partial,
            item: $item,
            relationExists: $this->relationExists($request),
            uploads: $uploads,
            media: fn (?string $library, string $id): ?array => $media->media($library, $id),
            valueExists: $valueExists,
            libraries: fn (array $libraries): array => array_map(
                fn (MediaResource $library): string => $library->id(),
                $this->resources->mediaLibraries($libraries),
            ),
            resourceId: fn (string $resource): ?string => $this->resources->resolve($resource)?->id(),
        );
    }

    /**
     * Resolves upload references (`{ "upload": "upl_…" }`) to completed uploads of the user,
     * once per upload: validation and saving ask for the same uploads.
     *
     * @return Closure(mixed): ?Upload
     */
    protected function uploadResolver(?Authenticatable $user): Closure
    {
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
