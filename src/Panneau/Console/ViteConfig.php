<?php

namespace Panneau\Console;

/**
 * Edits the source of a project's Vite config: adds an input of `laravel-vite-plugin` and a
 * plugin, keeping the formatting of the file. When the file doesn't have the expected shape,
 * the edits return null and the project is told what to add.
 *
 * @internal
 */
final class ViteConfig
{
    public function __construct(private string $source) {}

    public function source(): string
    {
        return $this->source;
    }

    /**
     * Adds an entry to the `input` of `laravel()`, an array or a single string.
     *
     * @return bool|null True when added, false when already there, null when not found.
     */
    public function addInput(string $input): ?bool
    {
        if (str_contains($this->source, $input)) {
            return false;
        }

        if (preg_match('/\binput\s*:\s*\[/', $this->source, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $quote = $this->quote(substr($this->source, $matches[0][1]));

            return $this->addListItem($matches[0][1] + strlen($matches[0][0]) - 1, $quote.$input.$quote);
        }

        if (preg_match('/(\binput\s*:\s*)([\'"])([^\'"]+)\2/', $this->source, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $quote = $matches[2][0];
            $replacement = $matches[1][0].'['.$quote.$matches[3][0].$quote.', '.$quote.$input.$quote.']';
            $this->source = substr_replace($this->source, $replacement, $matches[0][1], strlen($matches[0][0]));

            return true;
        }

        return null;
    }

    /**
     * Imports a plugin after the last import, and adds its call at the end of `plugins`.
     *
     * @return bool|null True when added, false when already imported, null when not found.
     */
    public function addPlugin(string $package, string $name, string $call): ?bool
    {
        if (str_contains($this->source, $package)) {
            return false;
        }

        if (preg_match_all('/^import\b[^;]*?[\'"][^\'"]+[\'"];?/ms', $this->source, $imports, PREG_OFFSET_CAPTURE) === 0
            || preg_match('/\bplugins\s*:\s*\[/', $this->source, $plugins, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $last = $imports[0][count($imports[0]) - 1];
        $end = $last[1] + strlen($last[0]);
        if ($plugins[0][1] < $end || $this->addListItem($plugins[0][1] + strlen($plugins[0][0]) - 1, $call) !== true) {
            return null;
        }

        // The plugins come after the imports: the offset of the last import hasn't moved
        $quote = $this->quote($last[0]);
        $semicolon = str_ends_with($last[0], ';') ? ';' : '';
        $this->source = substr_replace($this->source, "\nimport ".$name.' from '.$quote.$package.$quote.$semicolon, $end, 0);

        return true;
    }

    /**
     * Adds an item at the end of the list that opens at an offset, in the style of the list:
     * on its own line with the indentation of the last item, or inline.
     */
    private function addListItem(int $open, string $item): ?bool
    {
        $close = $this->closingBracket($open);
        if ($close === null) {
            return null;
        }
        $inner = substr($this->source, $open + 1, $close - $open - 1);
        $content = rtrim($inner);
        $after = substr($inner, strlen($content));

        if (trim($content) === '') {
            $replacement = $item;
        } elseif (preg_match('/^\s*\n([ \t]*)\S/', $inner, $first) === 1) {
            // One item per line, with the indentation of the first one
            $trailingComma = str_ends_with($content, ',');
            $replacement = $content.($trailingComma ? '' : ',')."\n".$first[1].$item.($trailingComma ? ',' : '').$after;
        } else {
            $replacement = rtrim($content, ', ').', '.$item;
        }

        $this->source = substr_replace($this->source, $replacement, $open + 1, $close - $open - 1);

        return true;
    }

    /**
     * The offset of the bracket that closes the one at an offset, skipping strings and comments.
     */
    private function closingBracket(int $open): ?int
    {
        $depth = 0;
        $length = strlen($this->source);
        for ($index = $open; $index < $length; $index++) {
            $char = $this->source[$index];
            $next = $this->source[$index + 1] ?? '';
            if ($char === '"' || $char === "'" || $char === '`') {
                $index = $this->stringEnd($index);
            } elseif ($char === '/' && $next === '/') {
                $end = strpos($this->source, "\n", $index);
                $index = $end === false ? $length : $end;
            } elseif ($char === '/' && $next === '*') {
                $end = strpos($this->source, '*/', $index + 2);
                $index = $end === false ? $length : $end + 1;
            } elseif ($char === '[' || $char === '(' || $char === '{') {
                $depth++;
            } elseif ($char === ']' || $char === ')' || $char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $char === ']' ? $index : null;
                }
            }
        }

        return null;
    }

    private function stringEnd(int $start): int
    {
        $quote = $this->source[$start];
        $length = strlen($this->source);
        for ($index = $start + 1; $index < $length; $index++) {
            if ($this->source[$index] === '\\') {
                $index++;
            } elseif ($this->source[$index] === $quote) {
                return $index;
            }
        }

        return $length;
    }

    /**
     * The quote of the first string in a piece of source, single by default.
     */
    private function quote(string $source): string
    {
        return preg_match('/[\'"]/', $source, $matches) === 1 ? $matches[0] : "'";
    }
}
