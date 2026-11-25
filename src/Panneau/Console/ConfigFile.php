<?php

namespace Panneau\Console;

use LogicException;
use Panneau\Upgrade\Php;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;

/**
 * Changes the values of a config file of the project (`return [...]`) in place, keeping its
 * comments and the rest of its code: a value is replaced, or added with its missing arrays.
 *
 * @internal
 */
final class ConfigFile
{
    public function __construct(private string $code) {}

    public function code(): string
    {
        return $this->code;
    }

    /**
     * The value at a dotted path as written: a literal, Unknown::Value for code that has to run
     * (`env()`), or null when the key is missing.
     */
    public function value(string $path): mixed
    {
        $expr = Php::get($this->root(), $path);

        return $expr === null ? null : Php::value($expr);
    }

    public function has(string $path): bool
    {
        return Php::get($this->root(), $path) !== null;
    }

    /**
     * Sets the value at a dotted path, as PHP code. Arrays of the path that are missing are
     * added. Returns whether the file changed.
     */
    public function set(string $path, string $value): bool
    {
        $keys = explode('.', $path);
        $parent = $this->root();
        $depth = 0;
        foreach ($keys as $index => $key) {
            $expr = Php::items($parent)[$key] ?? null;
            if ($expr === null) {
                break;
            }
            if ($index === count($keys) - 1) {
                $old = substr($this->code, $expr->getStartFilePos(), $expr->getEndFilePos() - $expr->getStartFilePos() + 1);
                if ($old === $value) {
                    return false;
                }
                $this->code = substr_replace($this->code, $value, $expr->getStartFilePos(), $expr->getEndFilePos() - $expr->getStartFilePos() + 1);

                return true;
            }
            if (! $expr instanceof Expr\Array_) {
                throw new LogicException(sprintf('%s is not an array: set its keys by hand.', implode('.', array_slice($keys, 0, $index + 1))));
            }
            $parent = $expr;
            $depth = $index + 1;
        }

        $this->insert($parent, array_slice($keys, $depth), $value);

        return true;
    }

    /**
     * Adds an item to an array, before its closing bracket.
     *
     * @param  list<string>  $keys  The key of the item, then the keys of the arrays inside it.
     */
    private function insert(Expr\Array_ $array, array $keys, string $value): void
    {
        $indent = $this->indentOf($array->getStartFilePos());
        $inner = $indent.'    ';
        $text = $value;
        foreach (array_reverse(array_slice($keys, 1), true) as $level => $key) {
            $levelIndent = $inner.str_repeat('    ', $level);
            $text = "[\n".$levelIndent.'    '.var_export($key, true).' => '.$text.",\n".$levelIndent.']';
        }
        $text = var_export($keys[0], true).' => '.$text.',';

        $items = $array->items;
        if ($items === []) {
            $open = $array->getStartFilePos() + 1;
            $this->code = substr($this->code, 0, $open)."\n".$inner.$text."\n".$indent.substr($this->code, $open, $array->getEndFilePos() - $open).substr($this->code, $array->getEndFilePos());

            return;
        }
        $last = end($items);
        $position = $last->getEndFilePos() + 1;
        // After the trailing comma of the last item, else with one
        if (preg_match('/\G\s*,/', $this->code, $matches, 0, $position) === 1) {
            $position += strlen($matches[0]);
        } else {
            $this->code = substr_replace($this->code, ',', $position, 0);
            $position++;
        }
        $this->code = substr_replace($this->code, "\n".$inner.$text, $position, 0);
    }

    private function indentOf(int $position): string
    {
        $start = strrpos(substr($this->code, 0, $position), "\n");
        $line = substr($this->code, $start === false ? 0 : $start + 1);

        return preg_match('/^[ \t]*/', $line, $matches) === 1 ? $matches[0] : '';
    }

    private function root(): Expr\Array_
    {
        $statements = (new ParserFactory)->createForHostVersion()->parse($this->code) ?? [];
        foreach ($statements as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof Expr\Array_) {
                return $statement->expr;
            }
        }

        throw new LogicException('The config file doesn\'t return an array.');
    }
}
