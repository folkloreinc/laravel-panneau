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
 * sortable columns. The `show`, `edit` and `delete` buttons of its `actions` column become
 * `ShowAction`, `EditAction` and `DeleteAction` in `actions()`, in their order. What's left
 * (filters, other actions, columns that aren't fields) is listed in a
 * `// TODO(panneau-5)` comment on `index()`, which is kept until it's moved; without anything
 * left, `index()` is removed.
 *
 * @internal
 */
final class ResourceIndexRector extends AbstractRector
{
    private const LIST = 'Panneau\\Lists\\ResourceList';

    /**
     * The buttons of the 1.x `actions` column, and their actions. `create` has none: the list
     * has its own button.
     */
    private const BUTTONS = [
        'show' => 'Panneau\\Actions\\ShowAction',
        'edit' => 'Panneau\\Actions\\EditAction',
        'delete' => 'Panneau\\Actions\\DeleteAction',
    ];

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
        $buttons = [];
        $actionsColumn = false;
        $left = [];
        foreach (Php::items($return->expr) as $key => $value) {
            if ($key === 'columns') {
                foreach (Php::items($value) as $column) {
                    $items = Php::items($column);
                    $id = Php::value($column) ?? null;
                    $id = is_string($id) ? $id : Php::value($items['id'] ?? $items['name'] ?? $items['path'] ?? null);
                    if (isset($items['actions']) || $id === 'actions') {
                        $actionsColumn = true;
                        $actions = Php::value($items['actions'] ?? null);
                        foreach (is_array($actions) ? $actions : [] as $action) {
                            if (is_string($action) && isset(self::BUTTONS[$action])) {
                                $buttons[] = self::BUTTONS[$action];
                            } elseif ($action !== 'create') {
                                $left[] = 'the '.(is_string($action) ? $action : 'custom').' action';
                            }
                        }
                        // The buttons can be on the column of a field, which stays a column
                        if ($id === 'actions' || ! is_string($id) || ! in_array($id, $fields, true)) {
                            continue;
                        }
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
        $buttons = array_values(array_unique($buttons));
        if ($columns === [] && $buttons === []) {
            return Todo::add($index, ['the 1.x index: write list() and actions() from it, then delete it']) ? $node : null;
        }

        $factory = new BuilderFactory;
        $methods = [];
        if ($columns !== []) {
            $list = new Expr\MethodCall(new Expr\Variable('list'), 'columns', [new Node\Arg($this->nodeFactory->createArray($columns))]);
            if ($sorts !== []) {
                $list = new Expr\MethodCall($list, 'sorts', [new Node\Arg($this->nodeFactory->createArray($sorts))]);
            }
            $methods[] = $factory->method('list')->makePublic()
                ->addParam($factory->param('list')->setType(new FullyQualified(self::LIST)))
                ->setReturnType(new FullyQualified(self::LIST))
                ->addStmt(new Stmt\Return_($list))
                ->getNode();
        }
        $actions = $node->getMethod('actions');
        if ($buttons !== [] && $actions === null) {
            $actions = $factory->method('actions')->makePublic()->setReturnType('array')
                ->addStmt(new Stmt\Return_(new Expr\Array_(
                    array_map(fn (string $class): Node\ArrayItem => $this->classItem($class), $buttons),
                    ['kind' => Expr\Array_::KIND_SHORT],
                )))
                ->getNode();
            $methods[] = $actions;
        } elseif ($buttons !== []) {
            $this->addActions($actions, $buttons);
        }
        if ($actionsColumn && $actions !== null && ! in_array(self::BUTTONS['show'], $buttons, true)) {
            Todo::add($actions, ["the 1.x list had no show button: if the panel of an item adds nothing to its form, open it in its form with open(): 'edit'"]);
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

    private function classItem(string $class): Node\ArrayItem
    {
        return new Node\ArrayItem(new Expr\ClassConstFetch(new FullyQualified($class), 'class'));
    }

    /**
     * Adds the actions of the 1.x buttons to an `actions()` the resource already has, after
     * its own, or lists them in a TODO when it doesn't return an array.
     *
     * @param  list<string>  $classes
     */
    private function addActions(Stmt\ClassMethod $method, array $classes): void
    {
        $return = (new NodeFinder)->findFirstInstanceOf($method->stmts ?? [], Stmt\Return_::class);
        if (! $return?->expr instanceof Expr\Array_) {
            Todo::add($method, ['add the buttons of the 1.x index to actions(): '.implode(', ', array_map(fn (string $class): string => class_basename($class), $classes))]);

            return;
        }
        $listed = [];
        foreach ($return->expr->items as $item) {
            if ($item->value instanceof Expr\ClassConstFetch && $item->value->class instanceof Node\Name) {
                $listed[] = $this->getName($item->value->class);
            }
        }
        foreach ($classes as $class) {
            if (! in_array($class, $listed, true)) {
                $return->expr->items[] = $this->classItem($class);
            }
        }
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
