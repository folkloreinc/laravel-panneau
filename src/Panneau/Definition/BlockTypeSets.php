<?php

namespace Panneau\Definition;

/**
 * Shares the lists of block types that repeat in a definition (panneau-js decision 0094): a
 * `repeater` whose `types` appear elsewhere names them with `typeSet`, a key of the
 * definition's `blockTypeSets`. The blocks of every type of a resource, and of the blocks that
 * hold blocks, are then sent once.
 *
 * @internal
 */
final class BlockTypeSets
{
    /**
     * The lists of block types by hash: with their nested lists named by hash.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $lists = [];

    /**
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * The key of each shared list by hash, and the lists by key.
     *
     * @var array<string, string>
     */
    private array $keys = [];

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private array $sets = [];

    /**
     * The definition with its repeated lists of block types in `blockTypeSets`.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function share(array $definition): array
    {
        $sets = new self;
        $named = $sets->name($definition);
        $sets->count($named);
        if (! in_array(true, array_map(fn (int $count): bool => $count > 1, $sets->counts), true)) {
            return $definition;
        }
        $shared = $sets->expand($named);
        if (! is_array($shared)) {
            return $definition;
        }
        $shared['blockTypeSets'] = $sets->sets;

        return $shared;
    }

    /**
     * Replaces, from the inside out, each list of block types by a hash of its content.
     */
    private function name(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        $node = array_map(fn (mixed $child): mixed => $this->name($child), $node);
        if (self::isRepeater($node)) {
            /** @var list<array<string, mixed>> $types */
            $types = $node['types'];
            $hash = md5((string) json_encode($types));
            $this->lists[$hash] = $types;
            unset($node['types']);
            $node['typeSet'] = $hash;
        }

        return $node;
    }

    /**
     * Counts where each list would be named once the lists are shared: in the definition, and
     * in each list once, however many times it repeats.
     */
    private function count(mixed $node): void
    {
        if (! is_array($node)) {
            return;
        }
        $hash = $node['typeSet'] ?? null;
        if (is_string($hash) && isset($this->lists[$hash])) {
            $this->counts[$hash] = ($this->counts[$hash] ?? 0) + 1;
            if ($this->counts[$hash] === 1) {
                $this->count($this->lists[$hash]);
            }
        }
        foreach ($node as $child) {
            $this->count($child);
        }
    }

    /**
     * Puts back the lists that appear once, and names the others by a readable key: the name
     * of the first field that has them (`blocks`, then `blocks-2`).
     */
    private function expand(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        $node = array_map(fn (mixed $child): mixed => $this->expand($child), $node);
        if (isset($node['typeSet'], $this->lists[$node['typeSet']]) && is_string($node['typeSet'])) {
            $hash = $node['typeSet'];
            if ($this->counts[$hash] > 1) {
                $node['typeSet'] = $this->key($hash, is_string($node['name'] ?? null) ? $node['name'] : 'blocks');
            } else {
                unset($node['typeSet']);
                $node['types'] = $this->expand($this->lists[$hash]);
            }
        }

        return $node;
    }

    private function key(string $hash, string $name): string
    {
        if (isset($this->keys[$hash])) {
            return $this->keys[$hash];
        }
        $key = $name;
        for ($index = 2; isset($this->sets[$key]); $index++) {
            $key = $name.'-'.$index;
        }
        // Reserved before its blocks expand: they can name other sets, never this one
        $this->keys[$hash] = $key;
        $this->sets[$key] = [];
        /** @var list<array<string, mixed>> $types */
        $types = $this->expand($this->lists[$hash]);
        $this->sets[$key] = $types;

        return $key;
    }

    /**
     * A field with a list of block types: a `name`, and `types` of blocks with an `id` and
     * `fields`. The types of a resource have no `name`.
     *
     * @param  array<mixed>  $node
     */
    private static function isRepeater(array $node): bool
    {
        if (! is_string($node['name'] ?? null) || ! is_string($node['type'] ?? null) || ! is_array($node['types'] ?? null) || $node['types'] === [] || ! array_is_list($node['types'])) {
            return false;
        }
        foreach ($node['types'] as $type) {
            if (! is_array($type) || ! isset($type['id'], $type['fields'])) {
                return false;
            }
        }

        return true;
    }
}
