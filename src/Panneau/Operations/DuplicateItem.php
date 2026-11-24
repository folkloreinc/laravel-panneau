<?php

namespace Panneau\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\Repositories\Duplicable;
use Panneau\Fields\Slug;
use Panneau\Operations\Concerns\ResolvesItems;
use Panneau\Resources\FieldChecks;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Copies an item through its repository, as `POST /resources/{resource}/{id}/duplicate` does:
 * the item is found within the resource's scope, the user must be able to view it (`view`) and
 * to duplicate it (`duplicate`), and each unique slug of the copy gets a free value.
 */
final class DuplicateItem
{
    use ResolvesItems;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly FieldChecks $checks,
    ) {}

    /**
     * @param  Request  $request  For the scope of the resource, the media of the values and the
     *                            hooks of the resource.
     * @return object The copy.
     *
     * @throws NotFoundHttpException When the resource or the item doesn't exist, or the item
     *                               is out of the scope.
     * @throws AuthorizationException When the user can't view or duplicate the item, or the
     *                                resource can't duplicate items.
     * @throws ConflictHttpException When the copy has a unique value another item has.
     */
    public function handle(?Authenticatable $user, Resource|string $resource, string $id, Request $request): object
    {
        $resource = $this->findResource($resource);
        $item = $this->findItem($resource, $id, $request);
        $repository = $this->resources->repository($resource);
        // The copy is answered in full: never for an item the user can't view
        if (! $this->authorizer->allowsItem($user, $resource, 'view', $item)) {
            throw new AuthorizationException;
        }
        $duplication = $this->resources->duplication($resource);
        if ($duplication === null || ! $repository instanceof Duplicable || ! $this->authorizer->allowsDuplicate($user, $resource, $item)) {
            throw new AuthorizationException;
        }

        try {
            return $repository->duplicate(
                $item,
                $resource->repositoryScope($request),
                $duplication,
                $this->copyValues($request, $resource, $item),
                fn (object $copy) => $resource->duplicating($item, $copy, $request),
                fn (object $copy) => $resource->duplicated($item, $copy, $request),
            );
        } catch (UniqueConstraintViolationException) {
            // A unique value that isn't a unique slug: nothing is saved
            throw new ConflictHttpException(__('The copy has a value that another item already has.'));
        }
    }

    /**
     * The values of the copy of an item: a free value for each unique slug.
     *
     * @return array<string, mixed>
     */
    private function copyValues(Request $request, Resource $resource, object $item): array
    {
        $exists = $this->checks->lookup($resource, $request, $item, [], $this->mediaReferences($request));
        $values = [];
        foreach ($resource->itemFields($item) as $field) {
            if ($field instanceof Slug && $field->isUnique()) {
                $values[$field->name()] = $field->copyValue(
                    $field->resolve($item),
                    fn (string $value, ?string $locale): bool => $exists($field->name(), $value, $locale, $field->uniqueScope()),
                );
            }
        }

        return $values;
    }
}
