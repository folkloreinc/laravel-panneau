<?php

namespace Panneau\Resources;

/**
 * What the copy of an item includes, for the built-in `duplicate`: a resource opts in by
 * returning one from `duplication()`. The repository copies the item's own values, and the
 * relations listed here; everything else is left out, unless the resource's
 * `duplicating()` or `duplicated()` hooks copy it.
 *
 * With `EloquentRepository`, `relations()` names relations of the model:
 * - `BelongsToMany` and `MorphToMany` (tags, media): the copy is attached to the same items,
 *   with the same pivot values (an order, a handle);
 * - `HasMany`, `MorphMany`, `HasOne` and `MorphOne` (blocks, sections): the children are
 *   copied, and `blocks.media` copies the relations of the copied children too;
 * - `BelongsTo` needs nothing: the copy keeps the same parent.
 */
final class Duplication
{
    /**
     * @param  list<string>  $relations
     */
    private function __construct(private readonly array $relations = []) {}

    public static function make(): self
    {
        return new self;
    }

    /**
     * The relations the copy includes, with dot paths for the relations of copied children.
     *
     * @param  list<string>  $relations
     */
    public function relations(array $relations): self
    {
        return new self(array_values(array_unique([...$this->relations, ...$relations])));
    }

    /**
     * @return list<string>
     */
    public function relationsToCopy(): array
    {
        return $this->relations;
    }
}
