<?php

namespace Panneau\Rector;

use Panneau\Upgrade\Php;
use Panneau\Upgrade\V1;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use Rector\Rector\AbstractRector;

/**
 * Converts the `index()` of a 1.x resource to `list()`: its columns that are fields, and its
 * sortable columns. A `delete` action becomes `DeleteAction` in `actions()`. What's left
 * (filters, other actions, columns that aren't fields) is listed in a
 * `// TODO(panneau-5)` comment on `index()`, which is kept until it's moved; without anything
 * left, `index()` is removed.
 *
 * @internal
 */
final class ResourceIndexRector extends AbstractRector
{
    private const LIST = 'Panneau\\Lists\\ResourceList';

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
        $index = $node->getMethod('index');
        if ($parent === null || $index === null || $node->getMethod('list') !== null || Todo::has($index)
            || ! $this->classes->isA($parent, [V1::RESOURCE, 'Panneau\\Resources\\Resource'])) {
            return null;
        }
        $return = (new NodeFinder)->findFirstInstanceOf($index->stmts ?? [], Stmt\Return_::class);
        if (! $return?->expr instanceof Expr\Array_) {
            Todo::add($index, ['the 1.x index is built at runtime: write list() and actions() from it, then delete it']);

            return $node;
        }

        $fields = $this->fieldNames($node);
        $columns = [];
        $sorts = [];
        $delete = false;
        $left = [];
        foreach (Php::items($return->expr) as $key => $value) {
            if ($key === 'columns') {
                foreach (Php::items($value) as $column) {
                    $items = Php::items($column);
                    $id = Php::value($column) ?? null;
                    $id = is_string($id) ? $id : Php::value($items['id'] ?? $items['name'] ?? $items['path'] ?? null);
                    if (isset($items['actions']) || $id === 'actions') {
                        $actions = Php::value($items['actions'] ?? null);
                        foreach (is_array($actions) ? $actions : [] as $action) {
                            if ($action === 'delete') {
                                $delete = true;
                            } elseif (! in_array($action, ['show', 'edit', 'create'], true)) {
                                $left[] = 'the '.(is_string($action) ? $action : 'custom').' action';
                            }
                        }

                        continue;
                    }
                    if (! is_string($id) || ! in_array($id, $fields, true)) {
                        $left[] = 'the column '.(is_string($id) ? $id : Php::describe($column, 40));

                        continue;
                    }
                    $columns[] = $id;
                    if (Php::value($items['sortable'] ?? null) === true) {
                        $sorts[] = $id;
                    }
                }

                continue;
            }
            if ($key === 'filters') {
                foreach (Php::items($value) as $filter) {
                    $items = Php::items($filter);
                    $name = Php::value($items['name'] ?? $items['id'] ?? null);
                    $component = Php::value($items['component'] ?? null);
                    $left[] = $component === 'search'
                        ? 'the search: $list->search([...]) with the fields to search'
                        : 'the filter '.(is_string($name) ? $name : '?').(is_string($component) ? " ({$component})" : '');
                }

                continue;
            }
            $left[] = match ($key) {
                'withFadedId' => null,
                'actionsProps' => str_contains(Php::describe($value), 'Duplicate') ? 'the duplicate action' : 'actionsProps',
                'actions' => 'the buttons of actions',
                'batchActions' => 'batchActions: actions on a selection',
                default => is_string($key) ? $key : null,
            };
        }
        $left = array_values(array_unique(array_filter($left)));
        if ($columns === [] && ! $delete) {
            return Todo::add($index, ['the 1.x index: write list() and actions() from it, then delete it']) ? $node : null;
        }

        $factory = new BuilderFactory;
        $list = new Expr\Variable('list');
        if ($columns !== []) {
            $list = new Expr\MethodCall($list, 'columns', [new Node\Arg($this->nodeFactory->createArray($columns))]);
        }
        if ($sorts !== []) {
            $list = new Expr\MethodCall($list, 'sorts', [new Node\Arg($this->nodeFactory->createArray($sorts))]);
        }
        $methods = [
            $factory->method('list')->makePublic()
                ->addParam($factory->param('list')->setType(new FullyQualified(self::LIST)))
                ->setReturnType(new FullyQualified(self::LIST))
                ->addStmt(new Stmt\Return_($list))
                ->getNode(),
        ];
        if ($delete && $node->getMethod('actions') === null) {
            $methods[] = $factory->method('actions')->makePublic()->setReturnType('array')
                ->addStmt(new Stmt\Return_(new Expr\Array_([
                    new Node\ArrayItem(new Expr\ClassConstFetch(new FullyQualified('Panneau\\Actions\\DeleteAction'), 'class')),
                ], ['kind' => Expr\Array_::KIND_SHORT])))
                ->getNode();
        }

        $stmts = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt !== $index) {
                $stmts[] = $stmt;

                continue;
            }
            if ($left !== []) {
                Todo::add($index, ['the rest of the 1.x index, then delete it: '.implode(', ', $left)]);
                $stmts[] = $index;
            }
            array_push($stmts, ...$methods);
        }
        $node->stmts = $stmts;

        return $node;
    }

    /**
     * The names of the fields that `fields()` makes.
     *
     * @return list<string>
     */
    private function fieldNames(Stmt\Class_ $class): array
    {
        $fields = $class->getMethod('fields');
        $return = (new NodeFinder)->findFirstInstanceOf($fields !== null ? $fields->stmts ?? [] : [], Stmt\Return_::class);
        $names = [];
        foreach (Php::items($return?->expr) as $field) {
            while ($field instanceof Expr\MethodCall) {
                $field = $field->var;
            }
            if (($field instanceof Expr\StaticCall || $field instanceof Expr\New_) && ($name = Php::value($field->getArgs()[0]->value ?? null)) && is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
