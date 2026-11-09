<?php

namespace Panneau\Rector;

use Illuminate\Support\Str;
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
 * Converts the 1.x resource types (subclasses of `Panneau\Support\ResourceType`) to 5.x types
 * (panneau-js decision 0077): a type that extends the 1.x class extends
 * `Panneau\Resources\ResourceType`, `name()` becomes `label()`, `canCreate` of `$settings`
 * becomes `canCreate()`, `$jsonResource` becomes `toItem()`, and a type without `id()` gets one that returns its 1.x id, which its
 * items store. Block types are left to `ClassesToMoveByHandRector`: they become the `BlockType`s
 * of a repeater.
 *
 * @internal
 */
final class ResourceTypesRector extends AbstractRector
{
    private const RESOURCE_TYPE = 'Panneau\\Resources\\ResourceType';

    private const AUTHENTICATABLE = 'Illuminate\\Contracts\\Auth\\Authenticatable';

    /**
     * The TODO that earlier releases of the rules left on resource types and `types()`.
     */
    public const OLD_TODO = 'resource types are not built yet in 5.x';

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
        $name = (string) $this->getName($node);
        if ($parent === null || ! $this->classes->isA($parent, [V1::RESOURCE_TYPE]) || self::isBlockType($name, $parent, $this->classes)) {
            return null;
        }
        // The TODO of earlier releases of the rules, which left the types to move by hand
        $changed = Todo::remove($node, self::OLD_TODO);
        if ($parent === V1::RESOURCE_TYPE) {
            $node->extends = new FullyQualified(self::RESOURCE_TYPE);
            $changed = true;
        }

        // The 1.x id of a type is its class name without `ResourceType`, in camel case: its
        // items store it, so a type that doesn't return it from id() keeps it, when the 5.x id
        // (the kebab case of the name without `Type`) differs
        if (! $node->isAbstract() && $node->getMethod('id') === null && ! $this->classes->declares($parent, 'id')) {
            $basename = class_basename($name);
            $id = Str::camel((string) preg_replace('/ResourceType$/', '', $basename));
            if ($id !== Str::kebab((string) preg_replace('/(Resource)?Type$/', '', $basename))) {
                $node->stmts = [...$this->method('id', 'string', new Node\Scalar\String_($id)), ...$node->stmts];
                $changed = true;
            }
        }

        $label = $node->getMethod('name');
        if ($label !== null && $node->getMethod('label') === null) {
            $label->name = new Node\Identifier('label');
            $label->returnType = new Node\Identifier('string');
            $changed = true;
        }

        $stmts = [];
        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Stmt\Property || ! $stmt->isStatic() || count($stmt->props) !== 1) {
                $stmts[] = $stmt;

                continue;
            }
            $property = $stmt->props[0]->name->toString();
            $value = $stmt->props[0]->default;
            if ($property === 'settings') {
                array_push($stmts, ...$this->convertSettings($node, $value));
                $changed = true;

                continue;
            }
            // The JsonResource of a type serializes its items, in place of the resource's: the
            // toItem() of the type
            $class = $value instanceof Expr\ClassConstFetch && $value->class instanceof Node\Name && $this->isName($value->name, 'class')
                ? $this->getName($value->class) : null;
            if ($property === 'jsonResource' && $class !== null && $node->getMethod('toItem') === null) {
                $factory = new BuilderFactory;
                $stmts[] = $factory->method('toItem')->makePublic()
                    ->addParam($factory->param('item')->setType('object'))
                    ->addParam($factory->param('values')->setType('array'))
                    ->setReturnType('array')
                    ->addStmt(new Stmt\Return_(new Expr\MethodCall(
                        new Expr\StaticCall(new FullyQualified($class), 'make', [new Node\Arg(new Expr\Variable('item'))]),
                        'resolve',
                    )))
                    ->getNode();
                $changed = true;

                continue;
            }
            // Lists serialize each item like a single item
            if ($property === 'jsonCollection') {
                $changed = true;

                continue;
            }
            $stmts[] = $stmt;
            if (in_array($property, ['repository', 'jsonResource'], true)) {
                $changed = Todo::add($stmt, [$property === 'repository'
                    ? 'the 1.x $repository of a type: the repository() of its resource serves every type'
                    : 'the 1.x $jsonResource of a type: move it to its toItem(), which gets the values of the resource']) || $changed;
            }
        }
        $node->stmts = $stmts;

        // 1.x types received their resource; 5.x types are resolved from the container alone
        $constructor = $node->getMethod('__construct');
        $readsResource = (new NodeFinder)->findFirst($node->stmts, fn (Node $node): bool => $node instanceof Expr\PropertyFetch
            && $node->var instanceof Expr\Variable && $node->var->name === 'this' && $this->isName($node->name, 'resource'));
        if ($constructor !== null || $readsResource !== null) {
            $changed = Todo::add($constructor ?? $node, ['5.x types are resolved from the container, without their resource ($this->resource)']) || $changed;
        }

        return $changed ? $node : null;
    }

    /**
     * Whether a 1.x type is a block type, which becomes a `BlockType` of a repeater rather than a
     * resource type: its name says so, or it extends the `BlockWithBlocks` of laravel-folklore.
     */
    public static function isBlockType(string $class, ?string $parent, ProjectClasses $classes): bool
    {
        return str_contains($class, 'Block') || $classes->isA($parent ?? $class, ['Folklore\\Panneau\\Resources\\BlockWithBlocks']);
    }

    /**
     * The methods and TODOs that replace the `$settings` of a type.
     *
     * @return list<Stmt>
     */
    private function convertSettings(Stmt\Class_ $class, ?Expr $settings): array
    {
        if (! $settings instanceof Expr\Array_) {
            return [$this->todo('the 1.x $settings of a type ('.Php::describe($settings).'): canCreate becomes canCreate()')];
        }
        $stmts = [];
        foreach ($settings->items as $item) {
            $key = Php::value($item->key);
            $value = Php::value($item->value);
            if ($key === 'canCreate' && $value === true) {
                continue;
            }
            if ($key === 'canCreate' && $value === false && $class->getMethod('canCreate') === null) {
                $factory = new BuilderFactory;
                $stmts[] = $factory->method('canCreate')->makePublic()
                    ->addParam($factory->param('user')->setType(new Node\NullableType(new FullyQualified(self::AUTHENTICATABLE))))
                    ->setReturnType('bool')
                    ->addStmt(new Stmt\Return_($this->nodeFactory->createFalse()))
                    ->getNode();

                continue;
            }
            $stmts[] = $this->todo(sprintf(
                'the 1.x setting %s of a type (%s): %s',
                is_string($key) ? $key : '?',
                Php::describe($item->value),
                $key === 'canCreate' ? 'canCreate()' : 'settings() of the resource, or a JS plugin',
            ));
        }

        return $stmts;
    }

    /**
     * @return list<Stmt\ClassMethod>
     */
    private function method(string $name, string $returnType, Expr $value): array
    {
        return [(new BuilderFactory)->method($name)->makePublic()->setReturnType($returnType)
            ->addStmt(new Stmt\Return_($value))->getNode()];
    }

    private function todo(string $todo): Stmt\Nop
    {
        $nop = new Stmt\Nop;
        Todo::add($nop, [$todo]);

        return $nop;
    }
}
