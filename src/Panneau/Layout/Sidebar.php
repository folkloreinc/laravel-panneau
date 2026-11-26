<?php

namespace Panneau\Layout;

/**
 * A panel of the form's aside. With a name, it is a panel that a component renders (panneau-js
 * decision 0075): a built-in one (`save`, `errors`, `publication`, `actions`) or one of a plugin,
 * which gets its children rendered and its props. Without the component, clients show the
 * children.
 *
 * ```php
 * Sidebar::panel('save');
 * Sidebar::panel('publication', 'Publication', [Field::make('published'), Field::make('published_at')]);
 * ```
 */
final class Sidebar extends Container
{
    private ?string $panel = null;

    private ?string $label = null;

    /**
     * @var array<string, mixed>
     */
    private array $props = [];

    /**
     * @param  list<Node>  $children
     */
    public static function make(array $children = []): self
    {
        return new self($children);
    }

    /**
     * @param  list<Node>  $children
     */
    public static function panel(string $panel, ?string $label = null, array $children = []): self
    {
        $sidebar = new self($children);
        $sidebar->panel = $panel;
        $sidebar->label = $label;

        return $sidebar;
    }

    public function type(): string
    {
        return 'sidebar';
    }

    /**
     * The title of the panel, which collapses it.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The props of the panel's component, such as the fields of the `publication` panel when they
     * aren't `published`, `published_at` and `status`.
     *
     * @param  array<string, mixed>  $props
     */
    public function props(array $props): self
    {
        $this->props = $props;

        return $this;
    }

    protected function properties(): array
    {
        return array_filter([
            'panel' => $this->panel,
            'label' => $this->label,
            'props' => count($this->props) > 0 ? $this->props : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
