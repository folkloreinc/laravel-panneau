<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Actions\Action;
use Panneau\Actions\ActionContext;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionValues;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\ResourceRepository;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Http\Controllers\Concerns\ResolvesResources;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /resources/{resource}/actions/{action}`: runs a server action on a selection of
 * items, or on the whole resource. With `dryRun`, counts the items it would run on and those it
 * would skip, without running it.
 *
 * @internal
 */
final class ActionController
{
    use ResolvesResources;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly DefinitionBuilder $definition,
        private readonly ValidationFactory $validator,
        private readonly Config $config,
        private readonly Uploads $uploads,
    ) {}

    public function __invoke(Request $request, string $resource, string $action): JsonResponse
    {
        $resource = $this->findResource($resource);
        $action = $this->resources->action($resource, $action);
        if (! $action instanceof Action) {
            throw new NotFoundHttpException;
        }
        $user = $request->user();
        if (! $this->authorizer->allowsResource($user, $resource, 'viewAny')
            || ! $this->authorizer->allowsActionOnResource($user, $action)) {
            throw new AuthorizationException;
        }

        // Refused before anything else: an action that doesn't answer dry runs never runs on one.
        $dryRun = $request->boolean('dryRun');
        if ($dryRun && ! $action->supportsDryRun()) {
            throw new BadRequestHttpException(__('This action does not answer dry runs.'));
        }

        $payload = $this->validatePayload($request, $action);
        // A dry run comes before the parameters: its values are not validated.
        $values = $dryRun ? [] : Form::validate(
            $this->validator,
            $action->fields(),
            is_array($payload['values'] ?? null) ? $payload['values'] : [],
            $this->validationContext($request, FormMode::Create),
        );

        $repository = $this->resources->repository($resource);
        $scope = $resource->scope($request);
        $result = new ActionResult(fn (object $item): string => $repository->id($item));
        $selection = $payload['selection'] ?? null;
        $items = is_array($selection)
            ? $this->selectItems($request, $resource, $action, $repository, $scope, $selection, $result)
            : [];
        $ids = array_map(fn (object $item): string => $repository->id($item), $items);

        if ($dryRun) {
            // The items the action would run on count as succeeded; nothing runs.
            return new JsonResponse($result->toArray($ids));
        }

        $action->setContext(new ActionContext($resource, $repository, $scope, $request, $user));
        $values = new ActionValues($values, fn (mixed $value) => $this->uploads->resolve($value, $user));
        $result = $action->handle(collect($items), $values, $result);

        return new JsonResponse($result->toArray($ids));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Action $action): array
    {
        $payload = $request->all();
        $rules = [
            'selection' => [$action->appliesToResource() ? 'nullable' : 'required', 'array'],
            'values' => ['sometimes', 'array'],
            'dryRun' => ['sometimes', 'boolean'],
        ];
        if (is_array($payload['selection'] ?? null)) {
            $rules += [
                'selection.ids' => ['sometimes', 'array', 'min:1'],
                'selection.ids.*' => ['required', 'distinct', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) && ! is_int($value)) {
                        $fail(__('The :attribute must be an id.'));
                    }
                }],
                'selection.query' => ['sometimes', 'array'],
                'selection.query.filter' => ['sometimes', 'array'],
                'selection.query.q' => ['sometimes', 'nullable', 'string'],
                'selection.except' => ['sometimes', 'array'],
                'selection.except.*' => ['string'],
            ];
        }
        $validator = $this->validator->make($payload, $rules);
        $validator->after(function ($validator) use ($payload, $action): void {
            $selection = $payload['selection'] ?? null;
            if ($selection === null) {
                return;
            }
            if (! $action->appliesToItems()) {
                $validator->errors()->add('selection', __('This action does not take a selection.'));
            } elseif (is_array($selection) && ! array_key_exists('ids', $selection) && ! array_key_exists('query', $selection)) {
                $validator->errors()->add('selection', __('The selection needs ids or a query.'));
            }
        });

        $validator->validate();

        // validated() would add empty keys for the wildcard rules of a missing selection.ids
        return $payload;
    }

    /**
     * Resolves the selection through the repository and the resource's scope. Items that are
     * not found or not allowed are reported as skipped, unless the selection is a single
     * item: then they are errors.
     *
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $selection
     * @return list<object>
     */
    private function selectItems(
        Request $request,
        Resource $resource,
        Action $action,
        ResourceRepository $repository,
        array $scope,
        array $selection,
        ActionResult $result,
    ): array {
        $limit = max(1, (int) $this->config->get('panneau.actions.sync_limit', 500));
        $user = $request->user();
        $items = [];

        if (isset($selection['ids']) && is_array($selection['ids'])) {
            $ids = array_values(array_unique(array_map(strval(...), $selection['ids'])));
            $this->ensureWithinLimit(count($ids), $limit);
            $single = count($ids) === 1;
            foreach ($ids as $id) {
                $item = $repository->find($id, $scope);
                if ($item === null) {
                    if ($single) {
                        throw new NotFoundHttpException;
                    }
                    $result->skipped($id, 'not_found');

                    continue;
                }
                if (! $this->authorizer->allowsAction($user, $resource, $action, $item)) {
                    if ($single) {
                        throw new AuthorizationException;
                    }
                    $result->skipped($item, 'forbidden');

                    continue;
                }
                $items[] = $item;
            }

            return $items;
        }

        $query = is_array($selection['query'] ?? null) ? $selection['query'] : [];
        $resourceQuery = ResourceQuery::fromParameters(
            ['filter' => $query['filter'] ?? [], 'q' => $query['q'] ?? null],
            $this->resources->queryOptions($resource),
            $scope,
        )
            ->withExcept(array_values(array_map(strval(...), is_array($selection['except'] ?? null) ? $selection['except'] : [])))
            ->withPage(1, $limit);
        $page = $repository->query($resourceQuery);
        $this->ensureWithinLimit($page->total(), $limit);

        foreach ($page->items() as $item) {
            if ($this->authorizer->allowsAction($user, $resource, $action, $item)) {
                $items[] = $item;
            } else {
                $result->skipped($item, 'forbidden');
            }
        }

        return $items;
    }

    private function ensureWithinLimit(int $count, int $limit): void
    {
        if ($count > $limit) {
            throw ValidationException::withMessages([
                'selection' => [__('This action can run on :limit items at most.', ['limit' => $limit])],
            ]);
        }
    }
}
