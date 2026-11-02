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
 * sortable columns. The built-in buttons of its `actions` column (`show`, `edit`, `duplicate`,
 * `delete`) become `itemActions()`, in their order, so that the list shows what it showed in
 * 1.x. Nothing is written when they are the four of the 5.x default, nor without an `actions`
 * column: the four are shown by abilities. What's left
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
     * The built-in item actions of 5.x, in the order the app shows them when the resource
     * doesn't say (`itemActions()` returns null). `create` has none: the list has its own button.
     */
    private const ITEM_ACTIONS = ['show', 'edit', 'duplicate', 'delete'];

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
            Todo::add($index, ['the 1.x index is built at runtime: write list() from it, and itemActions() if its rows had other buttons than show, edit and delete, then delete it']);

            return $node;
        }

        $fields = $this->fieldNames($node);
        $columns = [];
        $sorts = [];
        $buttons = [];
        $actionsColumn = false;
        $unresolved = false;
        $withoutButtons = false;
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
                        if (! is_array($actions)) {
                            // A constant or a call: the buttons are unknown
                            $unresolved = true;
                            $left[] = 'the buttons of the actions column, in itemActions()';
                        }
                        foreach (is_array($actions) ? $actions : [] as $action) {
                            if (is_string($action) && in_array($action, self::ITEM_ACTIONS, true)) {
                                $buttons[] = $action;
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
            if ($key === 'withoutActionsColumn') {
                $withoutButtons = Php::value($value) === true;

                continue;
            }
            $left[] = match ($key) {
                'withFadedId' => null,
                // The duplicate route: the duplicate button of the column decides
                'actionsProps' => str_contains(Php::describe($value), 'Duplicate') ? null : 'actionsProps',
                'actions' => 'the buttons of actions',
                'batchActions' => 'batchActions: actions on a selection',
                default => is_string($key) ? $key : null,
            };
        }
        $left = array_values(array_unique(array_filter($left)));
        $buttons = array_values(array_unique($buttons));
        $duplicates = in_array('duplicate', $buttons, true) || str_contains(Php::describe($return->expr), 'Duplicate');
        // Without an actions column, or with what the default shows, the default is right: it has
        // no duplicate until the resource opts in, so show, edit and delete are the default too
        $shown = array_values(array_diff($buttons, ['duplicate']));
        $writesButtons = $withoutButtons
            || ($actionsColumn && ! $unresolved && $shown !== array_values(array_diff(self::ITEM_ACTIONS, ['duplicate'])));
        if ($withoutButtons) {
            $buttons = [];
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
        $itemActions = $node->getMethod('itemActions');
        if ($writesButtons && $itemActions === null) {
            $itemActions = $factory->method('itemActions')->makePublic()->setReturnType('array')
                ->addStmt(new Stmt\Return_($this->nodeFactory->createArray($buttons)))
                ->getNode();
            $methods[] = $itemActions;
        } elseif ($writesButtons) {
            Todo::add($itemActions, ['the 1.x list showed these buttons in its rows: '.($buttons === [] ? 'none' : implode(', ', $buttons))]);
        }
        if ($itemActions !== null && $buttons !== [] && ! in_array('show', $buttons, true)) {
            Todo::add($itemActions, ["the 1.x list had no show button: if the panel of an item adds nothing to its form, open it in its form with open(): 'edit'"]);
        }

        if ($duplicates) {
            // Only the resource knows what a copy includes
            Todo::add($node, ['the 1.x list had a duplicate button: opt in with duplication(), and list what a copy includes (Duplication::make()->relations([...]), duplicating())']);
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
