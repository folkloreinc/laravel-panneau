<?php

namespace Panneau\Layout;

/**
 * The publication of an item (panneau-js decision 0065): the `publication` component of
 * `@panneau/forms`, which frames the fields of its children under the state of the item,
 * published, scheduled or not. Without the plugin, clients show the children.
 *
 * ```php
 * Publication::make([Field::make('published'), Field::make('published_at')]);
 * ```
 */
final class Publication extends Node
{
    /**
     * @var array<string, string>
     */
    private array $names = [];

    /**
     * @param  list<Node>  $children
     */
    public function __construct(private array $children = []) {}

    /**
     * @param  list<Node>  $children
     */
    public static function make(array $children = []): self
    {
        return new self($children);
    }

    public function type(): string
    {
        return 'component';
    }

    /**
     * The fields the state is read from, when they aren't `published`, `published_at` and
     * `status`.
     */
    public function fields(?string $published = null, ?string $publishedAt = null, ?string $status = null): self
    {
        $this->names = array_filter([
            'published' => $published,
            'publishedAt' => $publishedAt,
            'status' => $status,
        ], fn (?string $name): bool => $name !== null);

        return $this;
    }

    public function children(): array
    {
        return $this->children;
    }

    protected function properties(): array
    {
        return array_filter([
            'component' => 'publication',
            'props' => count($this->names) > 0 ? $this->names : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
