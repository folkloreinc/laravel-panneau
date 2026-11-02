<?php

namespace Panneau\Actions;

use Panneau\Contracts\ActionDeclaration;

/**
 * Replaces a built-in link of the protocol, `edit` or `show`, on items ([panneau-js decision
 * 0040]). Every resource has them without declaring them: declare one only to change it, with a
 * `url()` or a `route()` instead of the app's route, a `label()`, an `icon()` or a
 * `placement()`; what isn't set stays the app's.
 *
 * It doesn't run: the app shows it on the items whose abilities allow it (`update` for `edit`,
 * `view` for `show`), when `Resource::itemActions()` lists it.
 *
 * [panneau-js decision 0040]: https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0040-row-links-and-open-mode.md
 *
 * @phpstan-consistent-constructor
 */
abstract class BuiltInLink implements ActionDeclaration
{
    /**
     * The ids reserved for the built-in links: only `link` actions can have them.
     */
    public const IDS = ['show', 'edit'];

    protected ?string $label = null;

    protected ?string $icon = null;

    /**
     * @var list<ActionPlacement>|null
     */
    protected ?array $placement = null;

    protected ?string $url = null;

    protected ?string $route = null;

    public static function make(): static
    {
        return new static;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  list<ActionPlacement>  $placement
     */
    public function placement(array $placement): static
    {
        $this->placement = $placement;

        return $this;
    }

    /**
     * A URL template, with `{id}` for the item, instead of the app's route.
     */
    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * The name of a route in the definition, instead of the app's route.
     */
    public function route(string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function toDefinition(): array
    {
        return array_filter([
            'id' => $this->id(),
            'label' => $this->label,
            'icon' => $this->icon,
            'kind' => 'link',
            'scope' => [ActionScope::Item->value],
            'placement' => $this->placement !== null
                ? array_map(fn (ActionPlacement $placement): string => $placement->value, $this->placement)
                : null,
            'url' => $this->url,
            'route' => $this->route,
        ], fn (mixed $value): bool => $value !== null);
    }
}
