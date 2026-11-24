<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use MessageFormatter;
use Panneau\Actions\Action;
use Panneau\Actions\Confirm;
use Panneau\Agents\Confirmation;
use Panneau\Agents\ResourceDescription;
use Panneau\Agents\ToolCall;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Operations\DuplicateItem;
use Panneau\Operations\RunAction as RunActionOperation;
use Panneau\Operations\ShowItem;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `run_action`: runs an action of a resource, as `POST /resources/{resource}/actions/{action}`
 * does, or the built-in `duplicate`. An action that needs a confirmation (the `confirmation`
 * of `describe`: it declares one, or destroys what can't be undone) runs nothing at first: the
 * tool answers a preview and a signed `confirmation`, which a second call with the same
 * arguments sends back once the user agreed.
 */
final class RunAction extends BaseTool
{
    /**
     * The items the last call ran on, for `AgentToolCalled`.
     *
     * @var list<string>
     */
    private array $touched = [];

    public function __construct(
        ValidatorFactory $validator,
        Config $config,
        private readonly RunActionOperation $runAction,
        private readonly DuplicateItem $duplicateItem,
        private readonly ShowItem $showItem,
        private readonly ResourceRegistry $resources,
        private readonly DefinitionBuilder $definitions,
        private readonly ResourceDescription $description,
        private readonly Confirmation $confirmation,
    ) {
        parent::__construct($validator, $config);
    }

    public function name(): string
    {
        return 'run_action';
    }

    public function description(): string
    {
        return 'Runs an action of a resource on a selection of items, or on the whole resource. '
            .'`describe` with `parts: ["actions"]` lists the actions of a resource and their fields, the `values` they take. '
            .'Use `dryRun` to see what an action would do without running it. '
            .'An action that needs a confirmation, such as a destructive one, runs nothing at first: '
            .'it answers `confirmation_required`, with a message and a preview to show the user. '
            .'Only when the user agrees, call again with the same arguments and its `confirmation`.';
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function inputSchema(): array
    {
        $ids = ['type' => 'array', 'items' => ['type' => ['string', 'integer']]];

        return [
            'type' => 'object',
            'properties' => [
                'resource' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of the resource.'],
                'action' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of the action.'],
                'selection' => [
                    'type' => 'object',
                    'description' => 'The items: `{"ids": [...]}`, or the items of a list query `{"query": {"filter": {...}, "q": "..."}, "except": [...]}`. Omitted for actions on the whole resource.',
                    'properties' => [
                        'ids' => [...$ids, 'minItems' => 1],
                        'query' => [
                            'type' => 'object',
                            'properties' => ['filter' => ['type' => 'object'], 'q' => ['type' => 'string']],
                        ],
                        'except' => $ids,
                    ],
                ],
                'values' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'The values of the fields of the action.'],
                'dryRun' => ['type' => 'boolean', 'description' => 'Counts the items the action would run on and those it would skip, without running it. Only for actions with `dryRun`.'],
                'confirmation' => ['type' => 'string', 'minLength' => 1, 'description' => 'The `confirmation` of a `confirmation_required` answer: runs what it previewed, with the same arguments, within 5 minutes.'],
            ],
            'required' => ['resource', 'action'],
            'additionalProperties' => false,
        ];
    }

    protected function rules(): array
    {
        return [
            'resource' => ['required', 'string', 'min:1'],
            'action' => ['required', 'string', 'min:1'],
            'selection' => ['sometimes', 'array'],
            'values' => ['sometimes', 'array'],
            'dryRun' => ['sometimes', 'boolean'],
            'confirmation' => ['sometimes', 'string', 'min:1'],
        ];
    }

    protected function conflicts(array $arguments): array
    {
        return ($arguments['dryRun'] ?? false) === true && array_key_exists('confirmation', $arguments)
            ? ['confirmation' => __('A dry run runs nothing: it needs no confirmation.')]
            : [];
    }

    protected function run(ToolCall $call, array $arguments): array
    {
        $this->touched = [];
        $resource = $this->resources->find($arguments['resource']) ?? throw new NotFoundHttpException(sprintf('No resource "%s".', $arguments['resource']));
        /** @var array<string, mixed>|null $selection */
        $selection = $arguments['selection'] ?? null;
        /** @var array<string, mixed> $values */
        $values = $arguments['values'] ?? [];
        $dryRun = (bool) ($arguments['dryRun'] ?? false);
        $confirmation = $arguments['confirmation'] ?? null;

        if ($arguments['action'] === 'duplicate' && $this->resources->action($resource, 'duplicate') === null) {
            return $this->duplicate($call, $resource, $selection, $values, $dryRun, $confirmation);
        }

        $payload = array_filter(['selection' => $selection, 'values' => $values, 'dryRun' => $dryRun ?: null], fn (mixed $value): bool => $value !== null);
        $described = $this->describedAction($call->user, $resource, $arguments['action']);
        // An action the user can't run isn't described: it asks first, and the operation refuses it
        $needsConfirmation = $described === null || ($described['confirmation'] ?? false) === true;

        if (! $dryRun && $confirmation === null && $needsConfirmation) {
            $count = $this->runAction->count($call->user, $resource, $arguments['action'], $payload, $call->request);
            $preview = ($described['dryRun'] ?? false) === true
                ? $this->runAction->handle($call->user, $resource, $arguments['action'], [...$payload, 'dryRun' => true], $call->request, $call->agent)
                : array_filter(['count' => $count], fn (?int $value): bool => $value !== null);

            return [
                'status' => 'confirmation_required',
                'message' => $this->confirmationMessage($resource, $arguments['action'], $described, $count),
                'preview' => $preview === [] ? (object) [] : $preview,
                'confirmation' => $this->confirmation->sign($call->user, $call->agent, $resource->id(), $arguments['action'], $selection, $values),
            ];
        }
        if ($confirmation !== null) {
            $this->verify($call, $resource, $arguments['action'], $selection, $values, $confirmation, $needsConfirmation);
        }

        $run = $this->runAction->run($call->user, $resource, $arguments['action'], $payload, $call->request, $call->agent);
        $this->touched = $run['items'];

        return $run['result'];
    }

    protected function wrote(array $arguments, array $data): bool
    {
        return ($arguments['dryRun'] ?? false) !== true && ($data['status'] ?? null) !== 'confirmation_required';
    }

    protected function touchedItems(array $arguments, array $data): array
    {
        return $this->touched;
    }

    /**
     * The built-in `duplicate`, which isn't an action of the registry: a copy of one item.
     *
     * @param  array<string, mixed>|null  $selection
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function duplicate(ToolCall $call, Resource $resource, ?array $selection, array $values, bool $dryRun, ?string $confirmation): array
    {
        if ($dryRun) {
            throw new BadRequestHttpException(__('This action does not answer dry runs.'));
        }
        $ids = $selection['ids'] ?? null;
        $errors = array_filter([
            'selection' => is_array($ids) && count($ids) === 1 && (is_string($ids[0] ?? null) || is_int($ids[0] ?? null)) && count($selection ?? []) === 1
                ? null : __('`duplicate` copies one item: the selection is one id.'),
            'values' => $values === [] ? null : __('`duplicate` takes no values.'),
            'confirmation' => $confirmation === null ? null : __('This action runs without a confirmation.'),
        ]);
        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(fn (string $message): array => [$message], $errors));
        }

        $copy = $this->duplicateItem->handle($call->user, $resource, (string) $ids[0], $call->request);
        $id = $this->resources->repository($resource)->id($copy);
        $this->touched = [$id];

        return [
            'summary' => ['succeeded' => 1, 'failed' => 0, 'skipped' => 0],
            'item' => $this->showItem->handle($call->user, $resource, $id, $call->request)['data'],
        ];
    }

    /**
     * The action as `describe` gives it to the user, which says whether it needs a confirmation
     * and answers dry runs, or null when they can't run it.
     *
     * @return array<string, mixed>|null
     */
    private function describedAction(?Authenticatable $user, Resource $resource, string $action): ?array
    {
        $described = $this->description->resource(
            $this->definitions->resource($resource, $user),
            ['actions'],
            $this->resources->hasTrash($resource),
            $this->resources->duplicable($resource),
        );
        foreach (is_array($described['actions'] ?? null) ? $described['actions'] : [] as $candidate) {
            if (is_array($candidate) && ($candidate['id'] ?? null) === $action) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The action's own confirmation message, with `{count}`, or a sentence with its label.
     *
     * @param  array<string, mixed>|null  $described
     */
    private function confirmationMessage(Resource $resource, string $id, ?array $described, ?int $count): string
    {
        $action = $this->resources->action($resource, $id);
        $confirm = $action instanceof Action ? $action->confirm() : null;
        $message = $confirm instanceof Confirm ? ($confirm->toDefinition()['message'] ?? null) : null;
        if ($message !== null) {
            $formatted = MessageFormatter::formatMessage(app()->getLocale(), $message, ['count' => $count ?? 0]);

            return is_string($formatted) ? $formatted : $message;
        }
        $label = is_string($described['label'] ?? null) ? $described['label'] : $id;

        return $count === null
            ? __('Run “:action”?', ['action' => $label])
            : trans_choice('Run “:action” on :count item?|Run “:action” on :count items?', $count, ['action' => $label]);
    }

    /**
     * @param  array<string, mixed>|null  $selection
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException When the confirmation isn't one of these arguments.
     */
    private function verify(ToolCall $call, Resource $resource, string $action, ?array $selection, array $values, string $confirmation, bool $needed): void
    {
        if (! $needed) {
            throw ValidationException::withMessages(['confirmation' => [__('This action runs without a confirmation.')]]);
        }
        if (! $this->confirmation->verify($confirmation, $call->user, $call->agent, $resource->id(), $action, $selection, $values)) {
            throw ValidationException::withMessages(['confirmation' => [__('This confirmation is not valid for these arguments, or it expired: ask the user again.')]]);
        }
    }
}
