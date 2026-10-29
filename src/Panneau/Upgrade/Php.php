<?php

namespace Panneau\Upgrade;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Reads values from PHP expressions without running them.
 *
 * @internal
 */
final class Php
{
    /**
     * The value of a literal expression: a scalar, a class name, or an array of them.
     * Expressions that can't be read without running code give Unknown::Value, also inside
     * arrays.
     */
    public static function value(?Expr $expr): mixed
    {
        return match (true) {
            $expr === null => null,
            $expr instanceof Scalar\String_, $expr instanceof Scalar\Int_, $expr instanceof Scalar\Float_ => $expr->value,
            $expr instanceof Expr\ConstFetch => match (strtolower($expr->name->toString())) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => Unknown::Value,
            },
            $expr instanceof Expr\ClassConstFetch => self::className($expr) ?? Unknown::Value,
            $expr instanceof Expr\UnaryMinus && ($inner = self::value($expr->expr)) !== Unknown::Value && is_numeric($inner) => -$inner,
            $expr instanceof Expr\Array_ => self::arrayValue($expr),
            default => Unknown::Value,
        };
    }

    /**
     * @return array<array-key, mixed>|Unknown
     */
    private static function arrayValue(Expr\Array_ $array): array|Unknown
    {
        $values = [];
        foreach ($array->items as $item) {
            if ($item->unpack) {
                return Unknown::Value;
            }
            $value = self::value($item->value);
            if ($item->key === null) {
                $values[] = $value;

                continue;
            }
            $key = self::value($item->key);
            if (! is_string($key) && ! is_int($key)) {
                return Unknown::Value;
            }
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * The class of `Foo::class`.
     */
    public static function className(?Node $expr): ?string
    {
        if ($expr instanceof Expr\ClassConstFetch && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier && strtolower($expr->name->toString()) === 'class') {
            return $expr->class->toString();
        }
        if ($expr instanceof Scalar\String_ && preg_match('/^\\\\?[A-Z][A-Za-z0-9_]*(\\\\[A-Za-z0-9_]+)+$/', $expr->value) === 1) {
            return ltrim($expr->value, '\\');
        }

        return null;
    }

    /**
     * The items of an array, by key, as expressions. Items without a key are keyed by position.
     *
     * @return array<array-key, Expr>
     */
    public static function items(?Expr $array): array
    {
        if (! $array instanceof Expr\Array_) {
            return [];
        }
        $items = [];
        $position = 0;
        foreach ($array->items as $item) {
            if ($item->unpack) {
                continue;
            }
            $key = $item->key === null ? $position++ : self::value($item->key);
            if (is_string($key) || is_int($key)) {
                $items[$key] = $item->value;
            }
        }

        return $items;
    }

    /**
     * An expression in nested arrays, by a dotted path of keys.
     */
    public static function get(?Expr $array, string $path): ?Expr
    {
        foreach (explode('.', $path) as $key) {
            $array = self::items($array)[$key] ?? null;
            if ($array === null) {
                return null;
            }
        }

        return $array;
    }

    /**
     * An expression as PHP code, with fully qualified class names.
     */
    public static function print(Expr $expr): string
    {
        return (new Printer(['shortArraySyntax' => true]))->prettyPrintExpr($expr);
    }

    /**
     * A short description of an expression, for the report.
     */
    public static function describe(?Expr $expr, int $max = 60): string
    {
        if ($expr === null) {
            return 'null';
        }
        $class = self::className($expr);
        if ($class !== null) {
            return $class;
        }
        $code = preg_replace('/\s+/', ' ', self::print($expr)) ?? '';

        return mb_strlen($code) > $max ? mb_substr($code, 0, $max - 1).'…' : $code;
    }
}
