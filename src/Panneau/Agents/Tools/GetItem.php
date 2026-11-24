<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Panneau\Agents\ToolCall;
use Panneau\Operations\ShowItem;

/**
 * `get_item`: an item of a resource, with its `version`.
 */
final class GetItem extends BaseTool
{
    public function __construct(
        ValidatorFactory $validator,
        Config $config,
        private readonly ShowItem $showItem,
    ) {
        parent::__construct($validator, $config);
    }

    public function name(): string
    {
        return 'get_item';
    }

    public function description(): string
    {
        return 'Gets an item of a resource by its id, with every field you can see, or only `fields`. '
            .'Its `version` is what `save_item` needs to change it. Find ids with `find_items`.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of the resource.'],
                'id' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of the item.'],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string', 'minLength' => 1], 'minItems' => 1, 'description' => 'The fields to get. All of them by default.'],
            ],
            'required' => ['resource', 'id'],
            'additionalProperties' => false,
        ];
    }

    protected function rules(): array
    {
        return [
            'resource' => ['required', 'string', 'min:1'],
            'id' => ['required', 'string', 'min:1'],
            'fields' => ['sometimes', 'list', 'min:1'],
            'fields.*' => ['string', 'min:1'],
        ];
    }

    protected function run(ToolCall $call, array $arguments): array
    {
        return $this->showItem->handle(
            $call->user,
            $arguments['resource'],
            $arguments['id'],
            $call->request,
            $arguments['fields'] ?? null,
        )['data'];
    }
}
