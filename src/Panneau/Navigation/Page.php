<?php

namespace Panneau\Navigation;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Panneau\Dashboard\Widget;

/**
 * A page of the app shell, in the definition's `pages`: a component that a JS plugin registers
 * (or a built-in page), at `{basePath}/pages/{id}`, or at its own path. The page `home` is the
 * home page of the app.
 *
 * `Page::dashboard()` is a page of widgets, drawn by the app's built-in `dashboard` (panneau-js
 * decision 0084): related numbers, lines and breakdowns, on one page of the menu.
 */
final class Page
{
    /**
     * The component of the app's built-in dashboard.
     */
    public const DASHBOARD = 'dashboard';

    /**
     * What the names of `params()` look like, as the protocol's schema says.
     */
    public const PARAM_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private ?string $label = null;

    private ?string $path = null;

    /**
     * @var array<string, mixed>
     */
    private array $props = [];

    /**
     * @var list<string>
     */
    private array $params = [];

    /**
     * @var string|(Closure(?Authenticatable): bool)|null
     */
    private string|Closure|null $ability = null;

    /**
     * @var list<Widget>|Closure|null
     */
    private array|Closure|null $widgets = null;

    private function __construct(private readonly string $id, private readonly string $component)
    {
        if ($id === '') {
            throw new InvalidArgumentException('A page needs an id.');
        }
        if ($component === '') {
            throw new InvalidArgumentException(sprintf('The page "%s" needs a component.', $id));
        }
    }

    /**
     * A page, by id, rendered by a component that a JS plugin registers.
     */
    public static function make(string $id, string $component): self
    {
        return new self($id, $component);
    }

    /**
     * A dashboard page: its `widgets()`, drawn by the app's built-in `dashboard`, at
     * `{basePath}/pages/{id}`, with its label above them (panneau-js decision 0084). Its widgets
     * get their data from `GET {api}/dashboard/widgets/{widget}?page={id}`.
     *
     * `Page::dashboard('home')` is the home page: its widgets replace those of
     * `Panneau::dashboard()`, which can't be set with them.
     */
    public static function dashboard(string $id): self
    {
        return new self($id, self::DASHBOARD);
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * Whether the page is a dashboard, drawn by the app's built-in `dashboard`.
     */
    public function isDashboard(): bool
    {
        return $this->component === self::DASHBOARD;
    }

    /**
     * The label of the page, in menus and as its title.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The path of a custom page, instead of `{basePath}/pages/{id}`.
     */
    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * The props of the component.
     *
     * @param  array<string, mixed>  $props
     */
    public function props(array $props): self
    {
        $this->props = $props;

        return $this;
    }

    /**
     * The names of the path segments after the page: `['id']` makes
     * `{basePath}/pages/{page}/{id}`, and the component gets them as `params`. A page with
     * params is about one thing: menus can't link to it.
     *
     * @param  list<string>  $params
     */
    public function params(array $params): self
    {
        foreach ($params as $param) {
            if (preg_match(self::PARAM_PATTERN, $param) !== 1) {
                throw new InvalidArgumentException(sprintf('The page "%s" has the param %s: params are names such as "id".', $this->id, json_encode($param)));
            }
        }
        $this->params = $params;

        return $this;
    }

    /**
     * Shown only to the users who have an ability: a Gate ability (`'stats.view'`), or a closure
     * called through the container with the user as `$user`. For the others, the page has
     * `"abilities": { "view": false }`: the app leaves it out of menus, and its route shows a
     * forbidden page (panneau-js decision 0046). The page only hides: the endpoints its
     * component calls still check the user.
     *
     * @param  string|(Closure(?Authenticatable): bool)  $ability
     */
    public function can(string|Closure $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    /**
     * The widgets of a dashboard page (`Page::dashboard()`), in order: `Widget` builders, or a
     * closure called through the container with the user as `$user`, which returns them, as for
     * `Panneau::dashboard()`. Each user gets those whose ability they have (`Widget::can()`); a
     * user who can't view the page (`can()`) gets none.
     *
     * @param  list<Widget>|Closure  $widgets
     */
    public function widgets(array|Closure $widgets): self
    {
        if (! $this->isDashboard()) {
            throw new InvalidArgumentException(sprintf('The page "%s" is a "%s": only a dashboard page (Page::dashboard()) has widgets.', $this->id, $this->component));
        }
        $this->widgets = $widgets;

        return $this;
    }

    /**
     * @internal Read by `Navigation`.
     *
     * @return string|(Closure(?Authenticatable): bool)|null
     */
    public function ability(): string|Closure|null
    {
        return $this->ability;
    }

    /**
     * @internal Read by `Dashboard`: the widgets of `widgets()`, or null without them.
     *
     * @return list<Widget>|Closure|null
     */
    public function getWidgets(): array|Closure|null
    {
        return $this->widgets;
    }

    /**
     * The page in the definition, without its id, which is its key in `pages`. The widgets of a
     * dashboard page are added for each user, checked, by the definition.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'component' => $this->component,
            'path' => $this->path,
            'label' => $this->label,
            // Left out when empty, so that it isn't sent as a JSON list
            'props' => $this->props !== [] ? $this->props : null,
            'params' => $this->params !== [] ? $this->params : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
