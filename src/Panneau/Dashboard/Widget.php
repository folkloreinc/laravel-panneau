<?php

namespace Panneau\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Panneau\Contracts\WidgetData;

/**
 * A widget of the dashboard of the home page (panneau-js decision 0064): a `stat`, a `series` or
 * a `list`, or a type that a JS plugin draws. A `stat` or a `list` can read the list of a
 * resource (`resource()`); the others get their data from a class or a closure (`using()`),
 * which the app asks for at `GET {path}/api/dashboard/widgets/{id}`.
 */
final class Widget
{
    /**
     * The types that the app draws.
     */
    public const BUILT_IN_TYPES = ['stat', 'series', 'list'];

    /**
     * The built-in types that can read the list of a resource.
     */
    public const RESOURCE_TYPES = ['stat', 'list'];

    /**
     * The periods of the points of a `series`.
     */
    public const SERIES_PERIODS = ['day', 'week', 'month'];

    /**
     * The most items a `list` shows.
     */
    public const LIST_MAX_ITEMS = 5;

    /**
     * Query parameters that the app sets on the list of a widget on a resource.
     */
    public const RESERVED_QUERY_PARAMS = ['page', 'perPage', 'cursor'];

    private ?string $label = null;

    private ?int $width = null;

    /**
     * @var array<string, mixed>
     */
    private array $settings = [];

    private ?string $resource = null;

    /**
     * @var array<string, scalar|list<scalar>>
     */
    private array $query = [];

    /**
     * @var class-string<WidgetData>|Closure|null
     */
    private string|Closure|null $using = null;

    private ?int $cache = null;

    /**
     * @var string|(Closure(?Authenticatable): bool)|null
     */
    private string|Closure|null $ability = null;

    private function __construct(private readonly string $id, private readonly string $type)
    {
        if ($id === '') {
            throw new InvalidArgumentException('A widget needs an id.');
        }
        if ($type === '') {
            throw new InvalidArgumentException(sprintf('The widget "%s" needs a type.', $id));
        }
    }

    /**
     * A number in large type, with a line of help: a count of the list of a resource, or data
     * of its own.
     */
    public static function stat(string $id): self
    {
        return new self($id, 'stat');
    }

    /**
     * A line of values per day, week or month, with its total. Always data of its own.
     */
    public static function series(string $id): self
    {
        return new self($id, 'series');
    }

    /**
     * A few items: the first of the list of a resource, or data of its own.
     */
    public static function list(string $id): self
    {
        return new self($id, 'list');
    }

    /**
     * A widget of a type that a JS plugin draws, with data of its own.
     */
    public static function make(string $id, string $type): self
    {
        return new self($id, $type);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * The label of the widget: the title of its card, or what a `stat` counts.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The columns it takes in a grid of 12. Defaults to 3 for a `stat`, 6 for the others.
     */
    public function width(int $width): self
    {
        if ($width < 1 || $width > 12) {
            throw new InvalidArgumentException(sprintf('The widget "%s" has the width %d: widths go from 1 to 12 columns.', $this->id, $width));
        }
        $this->width = $width;

        return $this;
    }

    /**
     * The settings of a widget of a plugin, which its component reads.
     *
     * @param  array<string, mixed>  $settings
     */
    public function settings(array $settings): self
    {
        if (in_array($this->type, self::BUILT_IN_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no settings: give it a resource() or data with using().', $this->type, $this->id));
        }
        $this->settings = $settings;

        return $this;
    }

    /**
     * Reads the list of a resource, a class or an id, with a query written as its query
     * parameters: `['filter[status]' => 'to-judge', 'sort' => '-created_at']`. A `stat` shows
     * its total, a `list` its first items; both link to the list with that query. The user reads
     * the list as on its page, as soon as it changes: there is no endpoint and no cache.
     *
     * @param  array<string, mixed>  $query  Scalars, or lists of scalars.
     */
    public function resource(string $resource, array $query = []): self
    {
        if (! in_array($this->type, self::RESOURCE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" can\'t read a resource: only a stat and a list can. Give it data with using().', $this->type, $this->id));
        }
        if ($this->using !== null) {
            throw new InvalidArgumentException(sprintf('The widget "%s" has data of its own (using()): it can\'t also read a resource.', $this->id));
        }
        $checked = [];
        foreach ($query as $key => $value) {
            if (in_array($key, self::RESERVED_QUERY_PARAMS, true)) {
                throw new InvalidArgumentException(sprintf('The query of the widget "%s" sets "%s", which the app sets.', $this->id, $key));
            }
            if (is_scalar($value)) {
                $checked[$key] = $value;

                continue;
            }
            if (! is_array($value) || ! array_is_list($value) || count(array_filter($value, is_scalar(...))) !== count($value)) {
                throw new InvalidArgumentException(sprintf('The query of the widget "%s" has a value of "%s" that isn\'t a scalar or a list of them.', $this->id, $key));
            }
            /** @var list<scalar> $value */
            $checked[$key] = $value;
        }
        $this->resource = $resource;
        $this->query = $checked;

        return $this;
    }

    /**
     * The data of the widget: a class that implements `WidgetData`, built from the container,
     * or a closure called through the container with the user as `$user`. Either returns the
     * data in the shape of the type, with texts translated.
     *
     * @param  class-string<WidgetData>|Closure  $data
     */
    public function using(string|Closure $data): self
    {
        if (is_string($data) && ! is_subclass_of($data, WidgetData::class)) {
            throw new InvalidArgumentException(sprintf('The data of the widget "%s" is %s, which doesn\'t implement %s.', $this->id, $data, WidgetData::class));
        }
        if ($this->resource !== null) {
            throw new InvalidArgumentException(sprintf('The widget "%s" reads a resource: it can\'t also have data of its own.', $this->id));
        }
        $this->using = $data;

        return $this;
    }

    /**
     * Keeps a successful answer for some seconds, per user, locale and context: for a widget
     * that calls another service or computes something heavy. Errors are never kept. Don't cache
     * the items of a resource, which the user expects to see as soon as they change.
     */
    public function cache(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException(sprintf('The widget "%s" is cached for %d seconds: give it at least one.', $this->id, $seconds));
        }
        $this->cache = $seconds;

        return $this;
    }

    /**
     * Shown only to the users who have an ability: a Gate ability, or a closure called through
     * the container with the user as `$user`. For the others, the widget isn't in the
     * definition, and its data is a `404`.
     *
     * @param  string|(Closure(?Authenticatable): bool)  $ability
     */
    public function can(string|Closure $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * @internal Read by `Dashboard`.
     *
     * @return array<string, scalar|list<scalar>>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @internal Read by `Dashboard`.
     *
     * @return class-string<WidgetData>|Closure|null
     */
    public function getData(): string|Closure|null
    {
        return $this->using;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getCache(): ?int
    {
        return $this->cache;
    }

    /**
     * @internal Read by `Dashboard`.
     *
     * @return string|(Closure(?Authenticatable): bool)|null
     */
    public function ability(): string|Closure|null
    {
        return $this->ability;
    }

    /**
     * The widget in the definition, with the id of its resource.
     *
     * @internal Built by `Dashboard`, which checks the resource.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(?string $resource = null): array
    {
        $settings = $resource !== null
            ? array_filter(['resource' => $resource, 'query' => $this->query !== [] ? $this->query : null], fn (mixed $value): bool => $value !== null)
            : $this->settings;

        return array_filter([
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'width' => $this->width,
            // Left out when empty, so that it isn't sent as a JSON list
            'settings' => $settings !== [] ? $settings : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
