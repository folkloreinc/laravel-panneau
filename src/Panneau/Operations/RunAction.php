<?php

namespace Panneau\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Actions\Action;
use Panneau\Actions\ActionContext;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionValues;
use Panneau\Agents\AgentCaller;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\Repositories\Restorable;
use Panneau\Contracts\ResourceRepository;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Operations\Concerns\ResolvesItems;
use Panneau\Operations\Concerns\ValidatesValues;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Runs a server action, as `POST /resources/{resource}/actions/{action}` does: on a selection
 * of items, or on the whole resource. The user must be able to list the resource (`viewAny`)
 * and to run the action; items they can't run it on are skipped. With `dryRun`, counts the
 * items it would run on and those it would skip, without running it.
 *
 * The built-in `delete` and `restore` are reached as any other action, through
 * `ResourceRegistry::action()`.
 */
final class RunAction
{
    use ResolvesItems;
    use ValidatesValues;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly DefinitionBuilder $definition,
        private readonly ValidationFactory $validator,
        private readonly Config $config,
        private readonly Uploads $uploads,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  As the body of the request: `selection`, `values`
     *                                         and `dryRun`.
     * @param  Request  $request  For the scope of the resource, the media of the values, and
     *                            the context of the action.
     * @param  AgentCaller|null  $agent  The connection of the agent, for a run through a tool:
     *                                   the action reads it in its context.
     * @return array<string, mixed> The result of the action, or the counts of a dry run, as
     *                              the body of the response.
     *
     * @throws NotFoundHttpException When the resource or the action doesn't exist, or a
     *                               selection of a single id isn't found.
     * @throws AuthorizationException When the user can't list the resource or run the action,
     *                                or can't run it on a selection of a single id.
     * @throws BadRequestHttpException On a dry run of an action that doesn't answer dry runs.
     * @throws ValidationException When the payload or the values aren't valid, or the
     *                             selection is over `panneau.actions.sync_limit`.
     */
    public function handle(?Authenticatable $user, Resource|string $resource, string $action, array $payload, Request $request, ?AgentCaller $agent = null): array
    {
        return $this->run($user, $resource, $action, $payload, $request, $agent)['result'];
    }

    /**
     * Runs an action as handle() does, and tells the ids of the items it succeeded on, which
     * the result doesn't list: none for a dry run.
     *
     * @param  array<string, mixed>  $payload
     * @return array{result: array<string, mixed>, items: list<string>}
     *
     * @throws NotFoundHttpException|AuthorizationException|BadRequestHttpException|ValidationException As handle().
     */
    public function run(?Authenticatable $user, Resource|string $resource, string $action, array $payload, Request $request, ?AgentCaller $agent = null): array
    {
        [$resource, $action] = $this->authorizedAction($user, $resource, $action);

        // Refused before anything else: an action that doesn't answer dry runs never runs on one.
        $dryRun = filter_var($payload['dryRun'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($dryRun && ! $action->supportsDryRun()) {
            throw new BadRequestHttpException(__('This action does not answer dry runs.'));
        }

        $this->validatePayload($payload, $action);
        // A dry run comes before the parameters: its values are not validated.
        $uploads = $this->uploadResolver($user);
        $values = $dryRun ? [] : Form::validate(
            $this->validator,
            $action->fields(),
            is_array($payload['values'] ?? null) ? $payload['values'] : [],
            $this->validationContext($request, FormMode::Create, uploads: $uploads),
            $action->layout(),
        );

        $repository = $this->resources->repository($resource);
        $scope = $resource->repositoryScope($request);
        $result = new ActionResult(fn (object $item): string => $repository->id($item));
        $selection = $payload['selection'] ?? null;
        $items = is_array($selection)
            ? $this->selectItems($user, $resource, $action, $repository, $scope, $selection, $result)
            : [];
        $ids = array_map(fn (object $item): string => $repository->id($item), $items);

        if ($dryRun) {
            // The items the action would run on count as succeeded; nothing runs.
            return ['result' => $result->toArray($ids), 'items' => []];
        }

        $action->setContext(new ActionContext($resource, $repository, $scope, $request, $user, $agent));
        $values = new ActionValues($values, $uploads);
        $result = $action->handle(collect($items), $values, $result);
        // What can be undone says how: the inverse action, on the items that succeeded, up to
        // Action::UNDO_LIMIT of them (panneau-js decision 0063).
        $inverse = $action->inverse();
        if ($inverse !== null) {
            $result->undo($inverse, $action->inverseParams());
        }

        return ['result' => $result->toArray($ids), 'items' => $result->succeededIds($ids)];
    }

    /**
     * How many items the selection of a payload holds, checked as a run checks it (the user,
     * the payload, the values and `panneau.actions.sync_limit`), without running the action:
     * the number of distinct ids, or the total of the query. Items the user can't run the
     * action on are counted. Null for an action on the whole resource.
     *
     * @param  array<string, mixed>  $payload  As for handle(); `dryRun` is ignored.
     *
     * @throws NotFoundHttpException|AuthorizationException|ValidationException As handle().
     */
    public function count(?Authenticatable $user, Resource|string $resource, string $action, array $payload, Request $request): ?int
    {
        [$resource, $action] = $this->authorizedAction($user, $resource, $action);
        unset($payload['dryRun']);
        $this->validatePayload($payload, $action);
        Form::validate(
            $this->validator,
            $action->fields(),
            is_array($payload['values'] ?? null) ? $payload['values'] : [],
            $this->validationContext($request, FormMode::Create, uploads: $this->uploadResolver($user)),
            $action->layout(),
        );
        $selection = $payload['selection'] ?? null;
        if (! is_array($selection)) {
            return null;
        }
        $limit = max(1, (int) $this->config->get('panneau.actions.sync_limit', 500));
        if (isset($selection['ids']) && is_array($selection['ids'])) {
            $count = count(array_unique(array_map(strval(...), $selection['ids'])));
        } elseif ($action->findsDeletedItems()) {
            throw ValidationException::withMessages(['selection' => [__('The selection needs ids.')]]);
        } else {
            $count = $this->resources->repository($resource)->query($this->selectionQuery($resource, $selection, $resource->repositoryScope($request), 1))->total();
        }
        $this->ensureWithinLimit($count, $limit);

        return $count;
    }

    /**
     * The resource and its action, when the user can list the resource and run the action.
     *
     * @return array{resource, Action}
     */
    private function authorizedAction(?Authenticatable $user, Resource|string $resource, string $action): array
    {
        $resource = $this->findResource($resource);
        $action = $this->resources->action($resource, $action);
        if (! $action instanceof Action) {
            throw new NotFoundHttpException;
        }
        if (! $this->authorizer->allowsResource($user, $resource, 'viewAny')
            || ! $this->authorizer->allowsActionOnResource($user, $action, $resource)) {
            throw new AuthorizationException;
        }

        return [$resource, $action];
    }

    /**
     * The list query of a selection by query, on its first page of `$perPage` items.
     *
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $scope
     */
    private function selectionQuery(Resource $resource, array $selection, array $scope, int $perPage): ResourceQuery
    {
        $query = is_array($selection['query'] ?? null) ? $selection['query'] : [];

        return ResourceQuery::fromParameters(
            ['filter' => $query['filter'] ?? [], 'q' => $query['q'] ?? null],
            $this->resources->queryOptions($resource),
            $scope,
        )
            ->withExcept(array_values(array_map(strval(...), is_array($selection['except'] ?? null) ? $selection['except'] : [])))
            ->withPage(1, $perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(array $payload, Action $action): void
    {
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

        // Not validated(): it would add empty keys for the wildcard rules of a missing
        // selection.ids
        $validator->validate();
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
        ?Authenticatable $user,
        Resource $resource,
        Action $action,
        ResourceRepository $repository,
        array $scope,
        array $selection,
        ActionResult $result,
    ): array {
        $limit = max(1, (int) $this->config->get('panneau.actions.sync_limit', 500));
        $items = [];

        if (isset($selection['ids']) && is_array($selection['ids'])) {
            $ids = array_values(array_unique(array_map(strval(...), $selection['ids'])));
            $this->ensureWithinLimit(count($ids), $limit);
            $single = count($ids) === 1;
            foreach ($ids as $id) {
                $item = $action->findsDeletedItems() && $repository instanceof Restorable
                    ? $repository->findDeleted($id, $scope)
                    : $repository->find($id, $scope);
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

        // The trash has no list query: items are restored by their ids.
        if ($action->findsDeletedItems()) {
            throw ValidationException::withMessages(['selection' => [__('The selection needs ids.')]]);
        }

        $page = $repository->query($this->selectionQuery($resource, $selection, $scope, $limit));
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
