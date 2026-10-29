<?php

namespace Panneau\Rector;

use Panneau\Upgrade\V1;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use Rector\Rector\AbstractRector;

/**
 * Puts a `// TODO(panneau-5)` comment on the 1.x classes that only a person can move: resource
 * types, field classes and resource controllers. Their 5.x counterparts are configured
 * differently, so they keep their 1.x parent until then.
 *
 * @internal
 */
final class ClassesToMoveByHandRector extends AbstractRector
{
    public function __construct(private readonly ProjectClasses $classes) {}

    public function getNodeTypes(): array
    {
        return [Stmt\Class_::class];
    }

    /**
     * @param  Stmt\Class_  $node
     */
    public function refactor(Node $node): ?Node
    {
        $parent = $node->extends !== null ? (string) $this->getName($node->extends) : null;
        if ($parent === null || Todo::has($node)) {
            return null;
        }
        $todo = match (true) {
            $this->classes->isA($parent, [V1::RESOURCE_TYPE, 'Folklore\\Panneau\\Resources\\BlockWithBlocks']) => str_contains((string) $this->getName($node), 'Block')
                ? 'a 1.x resource type: a BlockType of the Repeater of the blocks (BlockTypes::register() of laravel-folklore 2), then delete it'
                : 'a 1.x resource type: resource types are not built yet in 5.x',
            $this->classes->isA($parent, [V1::RESOURCE_CONTROLLER]) => 'a 1.x resource controller: move its methods to hooks (creating(), updating(), saved()…) and actions of the resource, then delete it',
            default => $this->fieldTodo($parent),
        };
        if ($todo === null) {
            return null;
        }
        Todo::add($node, [$todo]);

        return $node;
    }

    private function fieldTodo(string $parent): ?string
    {
        $base = $this->classes->nearest($parent, array_keys(V1::FIELDS));
        if ($base === null) {
            return null;
        }
        [, $target] = V1::FIELDS[$base];

        return sprintf(
            'a 1.x field that extends %s: %s. 5.x fields are configured with methods: move field(), fields(), attributes() and component() there, or make it a field type with a JS plugin',
            class_basename($base),
            str_starts_with($target, 'manual: ') ? substr($target, 8) : $target,
        );
    }
}
