<?php

namespace Panneau\Upgrade;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * A class of the project, read from its source without loading it.
 *
 * @internal
 */
final class PhpClass
{
    /**
     * @param  class-string|string  $name
     * @param  list<string>  $interfaces
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $parent,
        public readonly array $interfaces,
        public readonly string $file,
        public readonly Class_ $node,
    ) {}

    public function line(): int
    {
        return $this->node->getStartLine();
    }

    public function location(): string
    {
        return $this->file.':'.$this->line();
    }

    public function shortName(): string
    {
        return class_basename($this->name);
    }

    public function isAbstract(): bool
    {
        return $this->node->isAbstract();
    }

    /**
     * The default value of a static property the class declares itself.
     */
    public function staticProperty(string $name): ?Expr
    {
        foreach ($this->node->getProperties() as $property) {
            if (! $property->isStatic()) {
                continue;
            }
            foreach ($property->props as $prop) {
                if ($prop->name->toString() === $name) {
                    return $prop->default ?? new Expr\ConstFetch(new Node\Name('null'));
                }
            }
        }

        return null;
    }

    /**
     * The names of the static properties the class declares itself.
     *
     * @return list<string>
     */
    public function staticProperties(): array
    {
        $names = [];
        foreach ($this->node->getProperties() as $property) {
            if ($property->isStatic()) {
                foreach ($property->props as $prop) {
                    $names[] = $prop->name->toString();
                }
            }
        }

        return $names;
    }

    public function method(string $name): ?ClassMethod
    {
        return $this->node->getMethod($name);
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return array_map(fn (ClassMethod $method): string => $method->name->toString(), $this->node->getMethods());
    }

    /**
     * The string a method returns, when it returns a literal: the component of a 1.x field.
     */
    public function returnedString(string $method): ?string
    {
        $return = $this->returned($method);

        return $return instanceof String_ ? $return->value : null;
    }

    /**
     * The expression of the first `return` of a method.
     */
    public function returned(string $method): ?Expr
    {
        $node = $this->method($method);
        if ($node === null) {
            return null;
        }
        $return = (new NodeFinder)->findFirstInstanceOf($node->stmts ?? [], Return_::class);

        return $return?->expr;
    }

    /**
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function find(string $class): array
    {
        return array_values((new NodeFinder)->findInstanceOf($this->node->stmts, $class));
    }
}
