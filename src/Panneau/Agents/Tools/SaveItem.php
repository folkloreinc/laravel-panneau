<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Panneau\Agents\ToolCall;
use Panneau\Auth\Authorizer;
use Panneau\Operations\Concerns\SerializesItems;
use Panneau\Operations\CreateItem;
use Panneau\Operations\UpdateItem;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `save_item`: creates an item, or changes one at the `version` the agent read. Saved as the
 * API saves it: authorized, validated, only the fields the user can edit. The result is the
 * saved item, as `get_item` gives it.
 */
final class SaveItem extends BaseTool
{
    use SerializesItems;

    public function __construct(
        ValidatorFactory $validator,
        Config $config,
        private readonly CreateItem $createItem,
        private readonly UpdateItem $updateItem,
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
    ) {
        parent::__construct($validator, $config);
    }

    public function name(): string
    {
        return 'save_item';
    }

    public function description(): string
    {
        return 'Creates an item of a resource, or changes one with its `id`. '
            .'Each value you send replaces the whole value of its field, a group or a list of blocks too: '
            .'send the complete value. The fields you leave out keep theirs. '
            .'To change an item, read it with `get_item` first and send its `version`: '
            .'if it changed since, you get the current item, to read again before saving. '
            .'`describe` tells the fields of a resource and their values.';
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of the resource.'],
                'id' => ['type' => 'string', 'minLength' => 1, 'description' => 'The item to change. Without it, creates one.'],
                'version' => ['type' => ['string', 'null'], 'description' => 'With `id`, required: the `version` of the item the agent read.'],
                'type' => ['type' => 'string', 'minLength' => 1, 'description' => 'Without `id`, in a resource with types: the type of the new item.'],
                'values' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'description' => 'By field. Each value replaces the whole value of its field, a group or a repeater too: the fields left out keep theirs.',
                ],
            ],
            'required' => ['resource', 'values'],
            'additionalProperties' => false,
        ];
    }

    protected function rules(): array
    {
        return [
            'resource' => ['required', 'string', 'min:1'],
            'id' => ['sometimes', 'string', 'min:1'],
            'version' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string', 'min:1'],
            'values' => ['present', 'array'],
        ];
    }

    protected function conflicts(array $arguments): array
    {
        $conflicts = [];
        $changes = array_key_exists('id', $arguments);
        // A `version` of null is given: the version of an item of a repository without versions
        if ($changes && ! array_key_exists('version', $arguments)) {
            $conflicts['version'] = __('Changing an item needs the `version` it was read at.');
        }
        if (! $changes && array_key_exists('version', $arguments)) {
            $conflicts['version'] = __('`version` is for changing an item: it needs an `id`.');
        }
        if ($changes && array_key_exists('type', $arguments)) {
            $conflicts['type'] = __('The type of an item is set when it is created.');
        }

        return $conflicts;
    }

    protected function run(ToolCall $call, array $arguments): array
    {
        /** @var array<string, mixed> $values */
        $values = $arguments['values'];
        if (isset($arguments['id'])) {
            $saved = $this->updateItem->handle(
                $call->user,
                $arguments['resource'],
                $arguments['id'],
                $values,
                self::ifMatch($arguments['version'] ?? null),
                $call->request,
                $call->agent,
            );
        } else {
            // The type of a new item is in its values, as in the body of `POST`
            if (isset($arguments['type'])) {
                $values[Resource::ITEM_TYPE] = $arguments['type'];
            }
            $saved = $this->createItem->handle($call->user, $arguments['resource'], $values, $call->request, $call->agent);
        }

        $resource = $this->resources->find($arguments['resource']) ?? throw new NotFoundHttpException;

        return $this->serializeItem($call->user, $resource, $saved['item'], $saved['references']);
    }

    protected function touchedItems(array $arguments, array $data): array
    {
        $id = $data['id'] ?? null;

        return is_int($id) || is_string($id) ? [(string) $id] : [];
    }

    /**
     * The `If-Match` of a version: quoted, so that it matches only that version, never as `*`.
     * A version of null is the empty tag, which no version matches: the version of an item of a
     * repository with versions is never skipped. A repository without versions checks none.
     */
    private static function ifMatch(?string $version): string
    {
        return '"'.($version ?? '').'"';
    }
}
