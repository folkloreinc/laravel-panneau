<?php

namespace Panneau\Http\Controllers;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Panneau\Auth\Authorizer;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Fields\Field;
use Panneau\Http\Controllers\Concerns\ResolvesResources;
use Panneau\Resources\FieldChecks;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /resources/{resource}/fields/{field}/check`: checks a value of a field with `check` as
 * it is typed, such as whether a slug is taken, and its URL. With the permission to create, or
 * to update the item `id`. Nothing is saved, and the answer isn't authoritative.
 *
 * @internal
 */
final class FieldCheckController
{
    use ResolvesResources;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly DefinitionBuilder $definition,
        private readonly ValidationFactory $validator,
        private readonly Uploads $uploads,
        private readonly FieldChecks $checks,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $resource = $this->findResource($this->routeParameter($request, 'resource'));
        $field = $this->routeParameter($request, 'field');
        // Only the fields at the top of the resource, or of one of its types
        $types = $this->typesWithField($resource, $field);
        if ($this->checkedField($resource, $field, null) === null && $types === []) {
            throw new NotFoundHttpException(sprintf('No field "%s" with a check.', $field));
        }

        $locales = $this->definition->locales();
        $payload = $this->validator->make($request->all(), [
            'value' => ['present', 'nullable', 'string'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in($locales)],
            'id' => ['sometimes', 'nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) && ! is_int($value)) {
                    $fail(__('The :attribute must be an id.'));
                }
            }],
            'values' => ['sometimes', 'nullable', 'array'],
        ])->validate();

        $id = $payload['id'] ?? null;
        $item = null;
        if ($id !== null) {
            $item = $this->findItem($resource, (string) $id, $request);
            $this->authorizeItem($request, $resource, 'update', $item);
        } else {
            $this->authorizeResource($request, $resource, 'create');
        }

        // The field of the item's type, or of the type of the new item
        $values = is_array($payload['values'] ?? null) ? $payload['values'] : [];
        $type = $item !== null ? $resource->typeOf($item) : null;
        if ($item === null && $types !== []) {
            $sent = $values[Resource::ITEM_TYPE] ?? null;
            $type = is_string($sent) && in_array($sent, $types, true) ? $sent : $types[0];
        }
        $checked = $this->checkedField($resource, $field, $type);
        if ($checked === null) {
            throw new NotFoundHttpException(sprintf('No field "%s" with a check.', $field));
        }

        // A localized value is checked in its locale, the default one when none is given
        $locale = null;
        if ($checked->isLocalized()) {
            $locale = is_string($payload['locale'] ?? null) ? $payload['locale'] : $locales[0];
        }

        return new JsonResponse($this->checks->check(
            $checked,
            $resource,
            $request,
            is_string($payload['value']) ? $payload['value'] : '',
            $locale,
            $item,
            $values,
            $type,
        ));
    }

    /**
     * A field with a check of the items of a type, or of the resource without a type.
     */
    private function checkedField(Resource $resource, string $name, ?string $type): ?Field
    {
        foreach ($resource->typeFields($type) as $field) {
            if ($field->name() === $name) {
                return $field->hasCheck() ? $field : null;
            }
        }

        return null;
    }

    /**
     * The types of a resource with a field of that name, with a check, after those of the
     * resource: their own, or one of the resource for some types only.
     *
     * @return list<string>
     */
    private function typesWithField(Resource $resource, string $name): array
    {
        $types = [];
        foreach ($resource->resourceTypes() as $type) {
            foreach ($resource->fieldsOfType($type) as $field) {
                if ($field->name() === $name && $field->hasCheck()) {
                    $types[] = $type->id();
                }
            }
        }

        return $types;
    }
}
