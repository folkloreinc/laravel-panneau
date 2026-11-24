<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Panneau\Agents\ResourceDescription;
use Panneau\Agents\ToolCall;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Fields\Field;
use Panneau\Fields\Group;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `describe`: the resources the user can list, or one of them, from their definition. The fields
 * of a resource give the `example` of their value (`Field::example()`), which the definition of
 * the app doesn't carry.
 */
final class Describe extends BaseTool
{
    public function __construct(
        ValidatorFactory $validator,
        Config $config,
        private readonly DefinitionBuilder $definitions,
        private readonly ResourceRegistry $resources,
        private readonly ResourceDescription $description,
    ) {
        parent::__construct($validator, $config);
    }

    public function name(): string
    {
        return 'describe';
    }

    public function description(): string
    {
        return 'Describes the content you can work with. Without `resource`, lists the resources you can read. '
            .'With `resource`, describes it: its fields, the filters and sorts of its list, and its actions (`parts` picks some of them). '
            .'Call it before `find_items` to know which filters, sorts and fields a resource takes.';
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
                'resource' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of a resource. Without it, lists the resources.'],
                'parts' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => ResourceDescription::PARTS],
                    'minItems' => 1,
                    'description' => 'With `resource`: the parts to describe. All of them by default.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function rules(): array
    {
        return [
            'resource' => ['sometimes', 'string', 'min:1'],
            'parts' => ['sometimes', 'list', 'min:1'],
            'parts.*' => ['string', 'in:'.implode(',', ResourceDescription::PARTS)],
        ];
    }

    protected function conflicts(array $arguments): array
    {
        return isset($arguments['parts']) && ! isset($arguments['resource'])
            ? ['parts' => __('`parts` describes a resource: it needs a `resource`.')]
            : [];
    }

    protected function run(ToolCall $call, array $arguments): array
    {
        $definition = $this->definitions->build($call->user);
        if (! isset($arguments['resource'])) {
            $list = ['resources' => $this->description->resources($definition)];
            if (isset($definition['context'])) {
                $list['context'] = $definition['context'];
            }

            return $list;
        }

        $id = $arguments['resource'];
        $resource = $this->resources->find($id);
        $described = null;
        foreach (is_array($definition['resources'] ?? null) ? $definition['resources'] : [] as $candidate) {
            if (is_array($candidate) && ($candidate['id'] ?? null) === $id && ($candidate['abilities']['viewAny'] ?? false) === true) {
                $described = $candidate;
            }
        }
        if ($resource === null || $described === null) {
            throw new NotFoundHttpException(sprintf('No resource "%s".', $id));
        }

        return $this->description->resource(
            $this->withExamples($described, $resource),
            $arguments['parts'] ?? ResourceDescription::PARTS,
            $this->resources->hasTrash($resource),
            $this->resources->duplicable($resource),
            is_array($definition['blockTypeSets'] ?? null) ? $definition['blockTypeSets'] : [],
        );
    }

    /**
     * The definition of a resource, with the `example` of each field that gives one, found by
     * name among the fields of the resource, of its types, and of their groups.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function withExamples(array $definition, Resource $resource): array
    {
        $definition['fields'] = self::examples($definition['fields'] ?? [], $resource->allFields());
        $types = [];
        foreach ($resource->resourceTypes() as $type) {
            $types[$type->id()] = $resource->fieldsOfType($type);
        }
        foreach (is_array($definition['types'] ?? null) ? $definition['types'] : [] as $index => $type) {
            if (is_array($type) && isset($types[$type['id'] ?? null])) {
                $definition['types'][$index]['fields'] = self::examples($type['fields'] ?? [], $types[$type['id']]);
            }
        }

        return $definition;
    }

    /**
     * @param  list<Field>  $fields
     */
    private static function examples(mixed $definitions, array $fields): mixed
    {
        if (! is_array($definitions)) {
            return $definitions;
        }
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->name()] = $field;
        }
        foreach ($definitions as $index => $definition) {
            $field = is_array($definition) && is_string($definition['name'] ?? null) ? ($byName[$definition['name']] ?? null) : null;
            if ($field === null) {
                continue;
            }
            $example = $field->example();
            if ($example !== null) {
                $definitions[$index]['example'] = $example;
            }
            if ($field instanceof Group && isset($definition['fields'])) {
                $definitions[$index]['fields'] = self::examples($definition['fields'], $field->getFields());
            }
        }

        return $definitions;
    }
}
