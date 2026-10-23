<?php

namespace Panneau\Http\Controllers;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Panneau\Auth\Authorizer;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Http\Controllers\Concerns\ResolvesResources;
use Panneau\Resources\FieldChecks;
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

    public function __invoke(Request $request, string $resource, string $field): JsonResponse
    {
        $resource = $this->findResource($resource);
        // Only the fields at the top of the resource
        $checked = $resource->field($field);
        if ($checked === null || ! $checked->hasCheck()) {
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
            is_array($payload['values'] ?? null) ? $payload['values'] : [],
        ));
    }
}
