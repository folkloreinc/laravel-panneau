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
 * Converts the static properties of a 1.x resource to the methods of a 5.x resource:
 * `$repository` to `repository()`, `$jsonResource` to `toItem()`, `$types` to `types()`,
 * `$settings` to what each setting becomes, and `name()` to `pluralLabel()`. A resource that
 * extends `Panneau\Support\Resource` extends `Panneau\Resources\Resource`.
 *
 * @internal
 */
final class ResourcePropertiesRector extends AbstractRector
{
    private const RESOURCE = 'Panneau\\Resources\\Resource';

    /**
     * The 1.x methods that read a property, and the 5.x method that replaces them.
     *
     * @var array<string, array{string, string}>
     */
    private const OVERRIDES = [
        'repository' => ['makeRepository', 'repository()'],
        'jsonResource' => ['makeJsonResource', 'toItem()'],
        'jsonCollection' => ['makeJsonCollection', 'toItem()'],
        'types' => ['getTypes', 'types()'],
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
        if ($parent === null || ! $this->classes->isA($parent, [V1::RESOURCE])) {
            return null;
        }
        $changed = false;
        if ($parent === V1::RESOURCE) {
            $node->extends = new FullyQualified(self::RESOURCE);
            $changed = true;
        }

        $reads = $this->staticReads($node);
        $stmts = [];
        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Stmt\Property || ! $stmt->isStatic() || count($stmt->props) !== 1) {
                $stmts[] = $stmt;

                continue;
            }
            $property = $stmt->props[0]->name->toString();
            $replacement = $this->convertProperty($node, $property, $stmt->props[0]->default);
            if ($replacement === null) {
                $stmts[] = $stmt;

                continue;
            }
            $changed = true;
            // A 1.x method that overrides what the property does, or code that reads it: the
            // property stays for them, and both get a TODO
            [$override, $replacedBy] = self::OVERRIDES[$property] ?? [null, null];
            $overridden = $override !== null ? $node->getMethod($override) : null;
            if ($overridden !== null || ($replacedBy !== null && in_array($property, $reads, true))) {
                $stmts[] = $stmt;
                $todo = sprintf(
                    '%s still uses the 1.x $%s: move it to %s, then delete the property',
                    $overridden !== null ? $override.'()' : 'static::$'.$property,
                    $property,
                    $replacedBy,
                );
                foreach ($replacement as $method) {
                    if ($method instanceof Stmt\ClassMethod) {
                        Todo::add($method, [$todo]);
                    }
                }
                if ($overridden !== null) {
                    Todo::add($overridden, [sprintf('a 1.x method that 5.x doesn\'t call: move what it does to %s, then delete it', $replacedBy)]);
                }
            }
            array_push($stmts, ...$replacement);
        }
        $node->stmts = $stmts;

        $name = $node->getMethod('name');
        if ($name !== null && $node->getMethod('pluralLabel') === null) {
            $name->name = new Node\Identifier('pluralLabel');
            $name->returnType = new Node\Identifier('string');
            $changed = true;
        }

        return $changed ? $node : null;
    }

    /**
     * The static properties of its own that a class reads (`static::$x`, `self::$x`).
     *
     * @return list<string>
     */
    private function staticReads(Stmt\Class_ $class): array
    {
        $reads = [];
        foreach ((new NodeFinder)->findInstanceOf($class->stmts, Expr\StaticPropertyFetch::class) as $fetch) {
            if ($fetch->class instanceof Node\Name && $fetch->name instanceof Node\VarLikeIdentifier
                && in_array(strtolower($fetch->class->toString()), ['static', 'self'], true)) {
                $reads[] = $fetch->name->toString();
            }
        }

        return array_values(array_unique($reads));
    }

    /**
     * The statements that replace a static property, or null to keep it.
     *
     * @return list<Stmt>|null
     */
    private function convertProperty(Stmt\Class_ $class, string $property, ?Expr $value): ?array
    {
        $value ??= $this->nodeFactory->createNull();
        $factory = new BuilderFactory;

        return match ($property) {
            'repository' => $this->method($class, 'repository', [], '?string', $value),
            'jsonResource' => $class->getMethod('toItem') !== null ? null : [
                $factory->method('toItem')->makePublic()
                    ->addParam($factory->param('item')->setType('object'))
                    ->setReturnType('array')
                    ->addStmt(new Stmt\Return_(new Expr\MethodCall(
                        new Expr\StaticCall($this->className($value), 'make', [new Node\Arg(new Expr\Variable('item'))]),
                        'resolve',
                    )))
                    ->getNode(),
            ],
            'jsonCollection', 'onlyDeclaredFields' => [],
            'controller' => [$this->todo(sprintf(
                '%s becomes hooks (creating(), updating(), saved()…) and actions: php artisan panneau:install --upgrade --check lists its methods',
                $this->classConstant($value) ?? Php::describe($value),
            ))],
            'types' => $this->method($class, 'types', [], 'array', $value, 'resource types are not built yet in 5.x: block types become BlockTypes of a Repeater'),
            'settings' => $this->convertSettings($class, $value),
            'index', 'components', 'forms', 'attributes' => [$this->todo(sprintf(
                'the 1.x $%s (%s): %s',
                $property,
                Php::describe($value),
                V1::RESOURCE_PROPERTIES[$property],
            ))],
            default => null,
        };
    }

    /**
     * @return list<Stmt>|null
     */
    private function convertSettings(Stmt\Class_ $class, Expr $settings): ?array
    {
        if (! $settings instanceof Expr\Array_) {
            return [$this->todo('the 1.x $settings ('.Php::describe($settings).'): what each setting becomes is in the migration guide')];
        }
        $stmts = [];
        $kept = [];
        foreach ($settings->items as $item) {
            $key = Php::value($item->key);
            $value = Php::value($item->value);
            if (is_string($key) && array_key_exists($key, V1::RESOURCE_DEFAULT_SETTINGS) && $value === V1::RESOURCE_DEFAULT_SETTINGS[$key]) {
                continue;
            }
            $stmts[] = match ($key) {
                'canCreate' => $this->todo('canCreate is false: the create() of the policy denies it'),
                'hideInNavbar' => $this->todo('hideInNavbar is true: the navigation is not configurable yet in 5.x'),
                'indexIsPaginated' => $this->todo('indexIsPaginated is false: 5.x lists are paginated'),
                default => null,
            };
            if (! in_array($key, ['canCreate', 'hideInNavbar', 'indexIsPaginated'], true)) {
                $kept[] = $item;
            }
        }
        $stmts = array_values(array_filter($stmts));
        if ($kept !== []) {
            $method = $this->method($class, 'settings', [], 'array', new Expr\Array_($kept, ['kind' => Expr\Array_::KIND_SHORT]));
            if ($method === null) {
                return null;
            }
            array_push($stmts, ...$method);
        }

        return $stmts;
    }

    /**
     * A method that returns the value, unless the class already has one of that name.
     *
     * @param  list<Node\Param>  $params
     * @return list<Stmt>|null
     */
    private function method(Stmt\Class_ $class, string $name, array $params, string $returnType, Expr $value, ?string $todo = null): ?array
    {
        if ($class->getMethod($name) !== null) {
            return null;
        }
        $method = (new BuilderFactory)->method($name)->makePublic()->addParams($params)->setReturnType($returnType)
            ->addStmt(new Stmt\Return_($value))->getNode();
        if ($todo !== null) {
            Todo::add($method, [$todo]);
        }

        return [$method];
    }

    private function className(Expr $value): Node\Name|Expr
    {
        $class = $this->classConstant($value);

        return $class !== null ? new FullyQualified($class) : $value;
    }

    /**
     * The class of `Foo::class`, resolved.
     */
    private function classConstant(Expr $value): ?string
    {
        return $value instanceof Expr\ClassConstFetch && $value->class instanceof Node\Name && $this->isName($value->name, 'class')
            ? $this->getName($value->class)
            : null;
    }

    private function todo(string $todo): Stmt\Nop
    {
        $nop = new Stmt\Nop;
        Todo::add($nop, [$todo]);

        return $nop;
    }
}
