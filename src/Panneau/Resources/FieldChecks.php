<?php

namespace Panneau\Resources;

use Closure;
use Illuminate\Http\Request;
use LogicException;
use Panneau\Contracts\Repositories\ChecksSlugs;
use Panneau\Fields\Field;
use Panneau\Fields\Relation;
use Panneau\Forms\FieldCheck;
use Panneau\Forms\ValidationContext;
use Panneau\Media\MediaReferences;
use Panneau\Repositories\EloquentRepository;

/**
 * What fields with `check` and unique slugs need from a resource: the form's values, and
 * whether another item has a value.
 *
 * @internal
 */
final class FieldChecks
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ItemSerializer $serializer,
    ) {}

    /**
     * Answers the check of a value of a field at the top of a resource.
     *
     * @param  array<array-key, mixed>  $input  The form's values the client sent.
     * @return array<string, mixed>
     */
    public function check(Field $field, Resource $resource, Request $request, string $value, ?string $locale, ?object $item, array $input): array
    {
        $values = $this->formValues($resource, $item, $input, new MediaReferences($this->resources, $request));
        $scope = $resource->repositoryScope($request);
        $id = $item !== null ? $this->resources->repository($resource)->id($item) : null;

        return $field->check(new FieldCheck(
            $value,
            $locale,
            $id,
            $values,
            fn (string $candidate, ?Closure $constrain): bool => $this->exists($resource, $field->name(), $candidate, $locale, $id, $scope, $values, $constrain),
        ));
    }

    /**
     * Whether another item of the resource has a value, for the validation of a create or an
     * update: `ValidationContext::$valueExists`. The form's values are read once, when a value
     * is first looked up.
     *
     * @param  array<array-key, mixed>  $input  The values of the request.
     * @return Closure(string, string, ?string, ?Closure): bool
     */
    public function lookup(Resource $resource, Request $request, ?object $item, array $input, MediaReferences $references): Closure
    {
        $values = null;

        return function (string $field, string $value, ?string $locale, ?Closure $constrain) use ($resource, $request, $item, $input, $references, &$values): bool {
            $values ??= $this->formValues($resource, $item, $input, $references);
            $id = $item !== null ? $this->resources->repository($resource)->id($item) : null;

            return $this->exists($resource, $field, $value, $locale, $id, $resource->repositoryScope($request), $values, $constrain);
        };
    }

    /**
     * Whether an item of the resource other than `$exceptId`, within the scope, has a value of
     * a field: through the repository's `slugExists()` when it implements `ChecksSlugs`, or the
     * query of an `EloquentRepository`, narrowed by the closure.
     *
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $values
     */
    public function exists(Resource $resource, string $field, string $value, ?string $locale, ?string $exceptId, array $scope, array $values, ?Closure $constrain = null): bool
    {
        $repository = $this->resources->repository($resource);
        if ($repository instanceof ChecksSlugs) {
            return $repository->slugExists($field, $value, $locale, $exceptId, $scope, $values);
        }
        if ($repository instanceof EloquentRepository) {
            $query = $repository->slugQuery($field, $value, $locale, $exceptId, $scope);
            if ($constrain !== null) {
                $constrain($query, $values);
            }

            return $query->exists();
        }

        throw new LogicException(sprintf(
            'The repository of the resource "%s" can\'t tell whether a slug is taken: implement %s.',
            $resource->id(),
            ChecksSlugs::class,
        ));
    }

    /**
     * The form's values, keyed by the fields at the top of the resource, as they would be
     * saved: the values sent, over those of the item being edited (an update sends only what
     * changed). Relations are ids. Values are not validated.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, mixed>
     */
    public function formValues(Resource $resource, ?object $item, array $input, MediaReferences $references): array
    {
        $current = $item !== null ? $this->serializer->serialize($resource, $item, references: $references) : [];
        $context = new ValidationContext;
        $values = [];
        foreach ($resource->allFields() as $field) {
            $name = $field->name();
            if (array_key_exists($name, $input)) {
                $value = $input[$name];
            } elseif (array_key_exists($name, $current)) {
                $value = $current[$name];
            } else {
                continue;
            }
            $values[$name] = $field instanceof Relation ? $field->extract($value, $context) : $value;
        }

        return $values;
    }
}
