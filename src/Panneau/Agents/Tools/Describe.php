<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Panneau\Agents\ResourceDescription;
use Panneau\Agents\ToolCall;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Resources\ResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `describe`: the resources the user can list, or one of them, from their definition.
 */
final class Describe extends ReadTool
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
            $described,
            $arguments['parts'] ?? ResourceDescription::PARTS,
            $this->resources->hasTrash($resource),
            $this->resources->duplicable($resource),
            is_array($definition['blockTypeSets'] ?? null) ? $definition['blockTypeSets'] : [],
        );
    }
}
