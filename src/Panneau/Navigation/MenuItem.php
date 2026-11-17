<?php

namespace Panneau\Navigation;

use InvalidArgumentException;

/**
 * An entry of a menu, in the definition's `menus`: every resource, a resource, a page, a link, a
 * separator or a group of entries. Plugins add their own types, with `make()`.
 */
final class MenuItem
{
    /**
     * The types the app shell knows. Other types are a plugin's.
     */
    public const TYPES = ['resources', 'resource', 'page', 'link', 'separator', 'group'];

    private ?string $label = null;

    private ?string $icon = null;

    private ?bool $external = null;

    private bool $collapsible = false;

    private bool $collapsed = false;

    private bool $emphasis = false;

    /**
     * @var list<MenuItem|array<string, mixed>>|null
     */
    private ?array $children = null;

    /**
     * @param  array<string, mixed>  $properties
     */
    private function __construct(private readonly string $type, private readonly array $properties = [])
    {
        if ($type === '') {
            throw new InvalidArgumentException('A menu item needs a type.');
        }
    }

    /**
     * An entry of a type a plugin provides, with the properties it reads.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function make(string $type, array $properties = []): self
    {
        return new self($type, $properties);
    }

    /**
     * Every resource the user can list, in the order they are registered.
     */
    public static function resources(): self
    {
        return new self('resources');
    }

    /**
     * The list of a resource, by class or by id.
     */
    public static function resource(string $resource): self
    {
        return new self('resource', ['resource' => $resource]);
    }

    /**
     * A page of the definition, by id. Pages with params can't be in a menu.
     */
    public static function page(string $page): self
    {
        return new self('page', ['page' => $page]);
    }

    /**
     * A link to a URL.
     */
    public static function link(string $label, string $url): self
    {
        return (new self('link', ['url' => $url]))->label($label);
    }

    public static function separator(): self
    {
        return new self('separator');
    }

    /**
     * A titled group of entries.
     *
     * @param  list<MenuItem|array<string, mixed>>  $children
     */
    public static function group(?string $label, array $children): self
    {
        $group = (new self('group'))->children($children);

        return $label !== null ? $group->label($label) : $group;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * The label of the entry. Resources and pages default to their own.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A Lucide icon name, such as `file-text`. Resources default to their own.
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Whether a link leaves the admin.
     */
    public function external(bool $external = true): self
    {
        $this->external = $external;

        return $this;
    }

    /**
     * A group whose links the user shows or hides, and the browser remembers it (panneau-js
     * decision 0079). Only a group with a label: the app ignores it on a group without one.
     */
    public function collapsible(bool $collapsible = true): self
    {
        $this->onlyFor(['group'], 'collapsible');
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * A collapsible group, closed until the user opens it, unless it holds the current page.
     */
    public function collapsed(bool $collapsed = true): self
    {
        $this->onlyFor(['group'], 'collapsed');
        $this->collapsed = $collapsed;

        return $this;
    }

    /**
     * A main entry, with its label in bold: a resource, a page or a link (panneau-js decision
     * 0079).
     */
    public function emphasis(bool $emphasis = true): self
    {
        $this->onlyFor(['resource', 'page', 'link'], 'emphasized');
        $this->emphasis = $emphasis;

        return $this;
    }

    /**
     * @param  list<string>  $types
     */
    private function onlyFor(array $types, string $option): void
    {
        if (! in_array($this->type, $types, true)) {
            throw new InvalidArgumentException(sprintf('A menu item of type "%s" can\'t be %s.', $this->type, $option));
        }
    }

    /**
     * The entries of a group, or of an entry of a plugin's type.
     *
     * @param  list<MenuItem|array<string, mixed>>  $children
     */
    public function children(array $children): self
    {
        $this->children = $children;

        return $this;
    }

    /**
     * The entry in the definition, with its references as they were given: the definition
     * turns resource classes into ids, and checks them.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        $definition = ['type' => $this->type] + $this->properties;
        foreach (['label' => $this->label, 'icon' => $this->icon, 'external' => $this->external] as $key => $value) {
            if ($value !== null) {
                $definition[$key] = $value;
            }
        }
        // Left out when false, so that menus without them stay as they were
        if ($this->collapsible || $this->collapsed) {
            $definition['collapsible'] = true;
        }
        if ($this->collapsed) {
            $definition['collapsed'] = true;
        }
        if ($this->emphasis) {
            $definition['emphasis'] = true;
        }
        if ($this->children !== null) {
            $definition['children'] = array_map(
                fn (MenuItem|array $child): array => $child instanceof MenuItem ? $child->toDefinition() : $child,
                $this->children,
            );
        }

        return $definition;
    }
}
