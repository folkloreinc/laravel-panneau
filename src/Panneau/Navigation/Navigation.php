<?php

namespace Panneau\Navigation;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;

/**
 * The pages and menus of the app shell: those of `panneau.pages` and `panneau.menus`, then
 * those declared with `Panneau::pages()` and `Panneau::menu()`, which replace a page of the same
 * id, or a menu of the same name. Closures are called each time the definition is built, through
 * the container, with the user as `$user`; a parameter of type `Panneau\Context\Context` gets
 * the current context.
 */
final class Navigation
{
    /**
     * @var list<array<array-key, mixed>|Closure>
     */
    private array $pages = [];

    /**
     * @var array<string, array<array-key, mixed>|Closure>
     */
    private array $menus = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    /**
     * Declares pages: `Page` builders, or pages in the protocol's shape keyed by id, or a
     * closure that returns them. They are added to the pages declared before, and replace
     * those of the same id.
     *
     * @param  array<array-key, Page|array<string, mixed>>|Closure  $pages
     */
    public function addPages(array|Closure $pages): void
    {
        $this->pages[] = $pages;
    }

    /**
     * Declares a menu: `MenuItem` builders, or items in the protocol's shape, or a closure that
     * returns them. It replaces the menu of the same name.
     *
     * @param  list<MenuItem|array<string, mixed>>|Closure  $items
     */
    public function setMenu(string $name, array|Closure $items): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('A menu needs a name, such as "main".');
        }
        $this->menus[$name] = $items;
    }

    /**
     * The pages for a user, by id, in the protocol's shape.
     *
     * @return array<string, array<string, mixed>>
     */
    public function pages(?Authenticatable $user = null): array
    {
        $config = $this->config->get('panneau.pages');
        $sources = [is_array($config) ? $config : [], ...$this->pages];
        $pages = [];
        foreach ($sources as $source) {
            foreach ($this->evaluate($source, $user, 'pages') as $key => $page) {
                [$id, $definition] = self::page($key, $page);
                // A page the user can't open (`Page::can()`, panneau-js decision 0046)
                if ($page instanceof Page && ! $this->allows($user, $page->ability())) {
                    $definition['abilities'] = ['view' => false];
                }
                $pages[$id] = $definition;
            }
        }

        return $pages;
    }

    /**
     * The menus for a user, by name, in the protocol's shape. References to resources are as
     * they were declared, classes or ids.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function menus(?Authenticatable $user = null): array
    {
        $config = $this->config->get('panneau.menus');
        $config = is_array($config) ? $config : [];
        foreach (array_keys($config) as $name) {
            if (! is_string($name) || $name === '') {
                throw new LogicException(sprintf('panneau.menus has a menu without a name (%s): key the menus by name, such as "main".', json_encode($name)));
            }
        }
        /** @var array<string, mixed> $menus */
        $menus = array_merge($config, $this->menus);
        $result = [];
        foreach ($menus as $name => $items) {
            $result[$name] = array_map(
                fn (mixed $item): array => self::menuItem($item, $name),
                array_values($this->evaluate($items, $user, sprintf('the menu "%s"', $name))),
            );
        }

        return $result;
    }

    /**
     * A declaration, or what its closure returns for the user.
     *
     * @return array<array-key, mixed>
     */
    private function evaluate(mixed $declaration, ?Authenticatable $user, string $what): array
    {
        if ($declaration instanceof Closure) {
            $declaration = $this->container->call($declaration, ['user' => $user]);
        }
        if (! is_array($declaration)) {
            throw new LogicException(sprintf('The declaration of %s gives %s instead of an array.', $what, get_debug_type($declaration)));
        }

        return $declaration;
    }

    /**
     * Whether the user has the ability of a page: a Gate ability, or a closure. Without one,
     * every user.
     *
     * @param  string|(Closure(?Authenticatable): bool)|null  $ability
     */
    private function allows(?Authenticatable $user, string|Closure|null $ability): bool
    {
        if ($ability === null) {
            return true;
        }
        if ($ability instanceof Closure) {
            return $this->container->call($ability, ['user' => $user]) === true;
        }

        return $this->container->make(Gate::class)->forUser($user)->allows($ability);
    }

    /**
     * A page and its id: a `Page`, or a page in the protocol's shape, keyed by its id (or with
     * an `id`, in a list).
     *
     * @return array{string, array<string, mixed>}
     */
    private static function page(int|string $key, mixed $page): array
    {
        if ($page instanceof Page) {
            return [$page->id(), $page->toDefinition()];
        }
        if (! is_array($page)) {
            throw new LogicException(sprintf('The page %s is a %s: pages are Page builders or arrays.', json_encode($key), get_debug_type($page)));
        }
        $id = is_string($key) ? $key : ($page['id'] ?? null);
        unset($page['id']);
        if (! is_string($id) || $id === '') {
            throw new LogicException(sprintf('The page %s has no id: key the pages by id.', json_encode($page)));
        }
        if (! is_string($page['component'] ?? null) || $page['component'] === '') {
            throw new LogicException(sprintf('The page "%s" has no component.', $id));
        }
        $params = $page['params'] ?? [];
        if (! is_array($params) || ! array_is_list($params) || array_filter($params, fn (mixed $param): bool => ! is_string($param) || preg_match(Page::PARAM_PATTERN, $param) !== 1) !== []) {
            throw new LogicException(sprintf('The page "%s" has the params %s: params are a list of names such as "id".', $id, json_encode($params)));
        }
        // Null values and empty maps are left out, so that `props` isn't sent as a JSON list
        $page = array_filter($page, fn (mixed $value): bool => $value !== null && $value !== []);

        return [$id, $page];
    }

    /**
     * An item of a menu in the protocol's shape, with its children.
     *
     * @return array<string, mixed>
     */
    private static function menuItem(mixed $item, string $menu): array
    {
        if ($item instanceof MenuItem) {
            $item = $item->toDefinition();
        }
        if (! is_array($item) || ! is_string($item['type'] ?? null) || $item['type'] === '') {
            throw new LogicException(sprintf('The menu "%s" has an item without a type: %s.', $menu, json_encode($item)));
        }
        $item = array_filter($item, fn (mixed $value): bool => $value !== null);
        if (isset($item['children'])) {
            if (! is_array($item['children'])) {
                throw new LogicException(sprintf('The menu "%s" has an item whose children are not a list: %s.', $menu, json_encode($item)));
            }
            $item['children'] = array_map(fn (mixed $child): array => self::menuItem($child, $menu), array_values($item['children']));
        }

        /** @var array<string, mixed> $item */
        return $item;
    }
}
