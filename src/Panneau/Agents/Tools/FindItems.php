<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Panneau\Agents\ToolCall;
use Panneau\Operations\ListItems;
use Panneau\Operations\Search;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Search\SearchQuery;

/**
 * `find_items`: the global search, or the list of a resource. Its items are lighter than those of
 * the API: the fields of the list's columns by default, and without their `abilities`, which
 * `get_item` gives.
 */
final class FindItems extends BaseTool
{
    /**
     * The most items a page holds (`AGENT_MAX_PER_PAGE` of the protocol).
     */
    public const MAX_PER_PAGE = 50;

    private const LIST_ARGUMENTS = ['filter', 'sort', 'page', 'cursor', 'perPage', 'fields'];

    public function __construct(
        ValidatorFactory $validator,
        Config $config,
        private readonly ListItems $listItems,
        private readonly Search $search,
        private readonly ResourceRegistry $resources,
    ) {
        parent::__construct($validator, $config);
    }

    public function name(): string
    {
        return 'find_items';
    }

    public function description(): string
    {
        return 'Finds items. Without `resource`, searches every searchable resource for `q`, at most `limit` items each. '
            .'With `resource`, lists its items, a page at a time: `filter`, `sort`, `q`, `page` or `cursor`, `perPage` (at most 50) and `fields`. '
            .'Items come with the fields of the list\'s columns, without their abilities. '
            .'Call `describe` with the resource first to know its filters, sorts and fields, then `get_item` to read an item in full, with its abilities.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function inputSchema(): array
    {
        $fields = ['type' => 'array', 'items' => ['type' => 'string', 'minLength' => 1], 'minItems' => 1];

        return [
            'type' => 'object',
            'properties' => [
                'resource' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of the resource to list. Without it, searches every resource.'],
                'q' => ['type' => 'string', 'description' => 'The text to search for. Required without `resource`.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => SearchQuery::MAX_LIMIT, 'description' => 'Without `resource`: the most items of each resource.'],
                'filter' => ['type' => 'object', 'description' => 'By filter id, as `describe` lists them.'],
                'sort' => ['type' => 'string', 'minLength' => 1, 'description' => 'A sort of the resource, `-` first for descending: `-published_at`.'],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'cursor' => ['type' => 'string', 'minLength' => 1, 'description' => 'In a list paged with cursors, the `next` cursor of the previous page.'],
                'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE],
                'fields' => [...$fields, 'description' => "The fields of each item. Defaults to those of the list's columns."],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function rules(): array
    {
        return [
            'resource' => ['sometimes', 'string', 'min:1'],
            'q' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.SearchQuery::MAX_LIMIT],
            'filter' => ['sometimes', 'array'],
            'sort' => ['sometimes', 'string', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'cursor' => ['sometimes', 'string', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'fields' => ['sometimes', 'list', 'min:1'],
            'fields.*' => ['string', 'min:1'],
        ];
    }

    protected function conflicts(array $arguments): array
    {
        $conflicts = [];
        if (! isset($arguments['resource'])) {
            if (! isset($arguments['q'])) {
                $conflicts['q'] = __('The global search needs `q`.');
            }
            foreach (self::LIST_ARGUMENTS as $name) {
                if (array_key_exists($name, $arguments)) {
                    $conflicts[$name] = __('`:name` lists a resource: it needs a `resource`.', ['name' => $name]);
                }
            }

            return $conflicts;
        }
        if (array_key_exists('limit', $arguments)) {
            $conflicts['limit'] = __('`limit` is for the global search: use `perPage`.');
        }
        if (array_key_exists('page', $arguments) && array_key_exists('cursor', $arguments)) {
            $conflicts['cursor'] = __('A page is asked by `page` or by `cursor`, not both.');
        }

        return $conflicts;
    }

    protected function run(ToolCall $call, array $arguments): array
    {
        if (! isset($arguments['resource'])) {
            // The parameters of the search are those of a query string
            $parameters = ['q' => $arguments['q']];
            if (isset($arguments['limit'])) {
                $parameters['limit'] = (string) $arguments['limit'];
            }

            $result = $this->search->handle($call->user, $parameters, $call->request, $this->columnFields(...));
            foreach ($result['data'] as $index => $group) {
                $result['data'][$index]['items'] = self::withoutAbilities($group['items']);
            }

            return $result;
        }

        $parameters = array_intersect_key($arguments, array_flip(['filter', 'sort', 'q', 'page', 'cursor']));
        $parameters['perPage'] = $arguments['perPage']
            ?? min((int) $this->config->get('panneau.list.per_page', 20), self::MAX_PER_PAGE);
        $resource = $this->resources->find($arguments['resource']);
        $fields = $arguments['fields'] ?? ($resource !== null ? $this->columnFields($resource) : null);
        if ($fields !== null) {
            $parameters['fields'] = implode(',', $fields);
        }

        $result = $this->listItems->handle($call->user, $resource ?? $arguments['resource'], $parameters, $call->request);
        $result['data'] = self::withoutAbilities($result['data']);

        return $result;
    }

    /**
     * The fields of the columns of a resource's list, or null when it declares none: every field.
     *
     * @return list<string>|null
     */
    private function columnFields(Resource $resource): ?array
    {
        $fields = [];
        foreach ($this->resources->list($resource)->getColumns() ?? [] as $column) {
            $field = $column->field();
            if ($field !== null && ! in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return $fields !== [] ? $fields : null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function withoutAbilities(array $items): array
    {
        return array_map(function (array $item): array {
            unset($item['abilities']);

            return $item;
        }, $items);
    }
}
