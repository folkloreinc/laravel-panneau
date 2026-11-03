<?php

namespace Panneau\Context;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use LogicException;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Exceptions\ContextRefusedException;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * Checks the values of the context for a user, param by param, and finds the choices of a param.
 * Each param gets the `Context` of the params before it: as `$context` in its closures, and as
 * the current `Context` of the request while its resource is queried, so that the resource's
 * `scope()` reads only those values.
 *
 * @internal
 */
final class ContextResolver
{
    /**
     * The name of the header of the context.
     */
    public const HEADER = 'Panneau-Context';

    /**
     * The most choices `GET /context/{param}/options` gives.
     */
    public const MAX_OPTIONS = 20;

    /**
     * How many items of a repository that isn't `Searchable` a search goes through, by label.
     */
    private const UNSEARCHABLE_SCAN = 100;

    public function __construct(
        private readonly Container $container,
        private readonly ContextRegistry $registry,
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
        private readonly Application $app,
    ) {}

    /**
     * The values of a `Panneau-Context` header, in the format of a query string: the first value
     * of a name wins, and empty values are left out.
     *
     * @return array<string, string>
     */
    public static function parseHeader(?string $header): array
    {
        $values = [];
        foreach (explode('&', (string) $header) as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $name = urldecode($parts[0]);
            $value = urldecode($parts[1] ?? '');
            if ($name !== '' && $value !== '' && ! array_key_exists($name, $values)) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * The values in the prefix of a path of the app (`client/4/contest/12/resources/posts`):
     * `{name}/{value}` for each param with a value, in the order of the params. Segments are
     * URL-encoded.
     *
     * @param  list<ContextParam>  $params
     * @return array<string, string>
     */
    public static function parsePath(string $path, array $params): array
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));
        $values = [];
        $position = 0;
        foreach ($params as $param) {
            if (($segments[$position] ?? null) === $param->name() && isset($segments[$position + 1])) {
                $values[$param->name()] = rawurldecode($segments[$position + 1]);
                $position += 2;
            }
        }

        return $values;
    }

    /**
     * Checks the values for the user, in the order of the params, and fills the current
     * `Context` with them. A required param without a value gets its default, if it has one.
     * Names that aren't params are ignored.
     *
     * @param  array<string, string>  $values
     *
     * @throws ContextRefusedException A value the user can't use. The current context is
     *                                 left empty.
     */
    public function resolve(array $values, ?Authenticatable $user, Request $request): Context
    {
        $current = $this->current();
        $current->replace(new Context);
        try {
            foreach ($this->registry->params($user) as $param) {
                $before = new Context;
                $before->replace($current);
                $value = $values[$param->name()] ?? null;
                $fixed = $this->fixedValue($param, $before, $user, $request);
                if ($fixed !== null) {
                    // The request fixes it: another value can't widen it, and the user must
                    // be allowed this one
                    if ($value !== null && $value !== $fixed) {
                        throw new ContextRefusedException($param->name());
                    }
                    $current->setFixed(
                        $param->name(),
                        $this->find($param, $fixed, $before, $user, $request) ?? throw new ContextRefusedException($param->name()),
                    );

                    continue;
                }
                if ($value !== null) {
                    $option = $this->find($param, $value, $before, $user, $request)
                        ?? throw new ContextRefusedException($param->name());
                } else {
                    $default = $this->defaultValue($param, $before, $user);
                    // A default the user can't use is left out: the client didn't send it
                    $option = $default !== null ? $this->find($param, $default, $before, $user, $request) : null;
                }
                if ($option !== null) {
                    $current->set($param->name(), $option);
                }
            }
        } catch (ContextRefusedException $exception) {
            $current->replace(new Context);

            throw $exception;
        }

        return $current;
    }

    /**
     * The first choices of a param that match `q`, for the values of the params before it in the
     * current context. Null for a param that isn't declared.
     *
     * @return list<ContextOption>|null
     */
    public function options(string $name, string $q, ?Authenticatable $user, Request $request): ?array
    {
        $params = $this->registry->params($user);
        $names = array_map(fn (ContextParam $param): string => $param->name(), $params);
        $index = array_search($name, $names, true);
        if ($index === false) {
            return null;
        }
        $param = $params[$index];
        $before = $this->current()->only(array_slice($names, 0, $index));
        $q = trim($q);

        return array_slice($this->search($param, $q, $before, $user, $request), 0, self::MAX_OPTIONS);
    }

    /**
     * The `context` of the definition, for the values of the current context: each param, its
     * inline options, its value and the label of its value. Null without a context.
     *
     * @return array{params: list<array<string, mixed>>}|null
     */
    public function definition(?Authenticatable $user): ?array
    {
        if (! $this->registry->declared()) {
            return null;
        }
        $current = $this->current();
        $names = [];
        $params = [];
        foreach ($this->registry->params($user) as $param) {
            $definition = $param->toDefinition();
            $fixed = $current->isFixed($param->name());
            if ($fixed) {
                $definition['fixed'] = true;
            } elseif ($param->inlineOptions() !== null) {
                $before = $current->only($names);
                $definition['options'] = array_map(
                    fn (ContextOption $option): array => $option->toArray(),
                    $this->inlineOptions($param, $before, $user),
                );
            }
            $option = $current->option($param->name());
            $definition['value'] = $option?->value;
            $definition['valueLabel'] = $option?->label;
            $params[] = $definition;
            $names[] = $param->name();
        }

        return $params !== [] ? ['params' => $params] : null;
    }

    /**
     * The choice of a value, if the user can use it.
     */
    private function find(ContextParam $param, string $value, Context $before, ?Authenticatable $user, Request $request): ?ContextOption
    {
        if ($param->inlineOptions() !== null) {
            foreach ($this->inlineOptions($param, $before, $user) as $option) {
                if ($option->value === $value) {
                    return $option;
                }
            }

            return null;
        }
        $resource = $this->resource($param);
        if ($resource !== null) {
            return $this->within($before, function () use ($resource, $value, $user, $request): ?ContextOption {
                $item = $this->resources->repository($resource)->find($value, $resource->repositoryScope($request));

                return $item !== null && $this->authorizer->allowsItem($user, $resource, 'view', $item)
                    ? $this->itemOption($resource, $item)
                    : null;
            });
        }
        $find = $param->findCallback();
        if ($find === null) {
            return null;
        }
        $found = $this->within($before, fn (): mixed => $this->call($find, $before, $user, ['value' => $value]));

        return match (true) {
            $found === null, $found === false => null,
            $found instanceof ContextOption => new ContextOption($value, $found->label, $found->description, $found->item),
            is_string($found) => new ContextOption($value, $found),
            is_array($found) => ContextOption::fromArray($found, $param->name(), $value),
            default => throw new LogicException(sprintf('The find() of the context param "%s" gives a %s: return a label, a choice or null.', $param->name(), get_debug_type($found))),
        };
    }

    /**
     * The choices of a param that match `q` (all of them for an empty `q`).
     *
     * @return list<ContextOption>
     */
    private function search(ContextParam $param, string $q, Context $before, ?Authenticatable $user, Request $request): array
    {
        if ($param->inlineOptions() !== null) {
            return self::matching($this->inlineOptions($param, $before, $user), $q);
        }
        $resource = $this->resource($param);
        if ($resource !== null) {
            return $this->within($before, fn (): array => $this->searchResource($resource, $q, $user, $request));
        }
        $search = $param->searchCallback();
        if ($search === null) {
            return [];
        }
        $options = $this->within($before, fn (): mixed => $this->call($search, $before, $user, ['q' => $q]));
        if (! is_array($options)) {
            throw new LogicException(sprintf('The search() of the context param "%s" gives %s instead of an array.', $param->name(), get_debug_type($options)));
        }

        return ContextOption::list($options, $param->name());
    }

    /**
     * The items of a resource that match `q`, within its scope, that the user can view: through
     * the search of its list (or its title), or by label for a repository that isn't
     * `Searchable`.
     *
     * @return list<ContextOption>
     */
    private function searchResource(Resource $resource, string $q, ?Authenticatable $user, Request $request): array
    {
        $repository = $this->resources->repository($resource);
        $options = $this->resources->queryOptions($resource);
        $searchable = $repository instanceof Searchable;
        $title = $resource->itemTitle();
        $searchFields = $options->searchFields !== [] ? $options->searchFields : ($title !== null ? [$title] : []);
        $page = $repository->query(new ResourceQuery(
            page: 1,
            perPage: $q !== '' && ! $searchable ? self::UNSEARCHABLE_SCAN : self::MAX_OPTIONS,
            sorts: $options->defaultSort,
            search: $q !== '' && $searchable ? mb_substr($q, 0, ResourceQuery::MAX_SEARCH_LENGTH) : null,
            searchFields: $searchFields,
            scope: $resource->repositoryScope($request),
        ));
        $choices = [];
        foreach ($page->items() as $item) {
            if ($this->authorizer->allowsItem($user, $resource, 'view', $item)) {
                $choices[] = $this->itemOption($resource, $item);
            }
        }

        return $searchable ? $choices : self::matching($choices, $q);
    }

    /**
     * The choice of an item: its id, and its title as its label.
     */
    private function itemOption(Resource $resource, object $item): ContextOption
    {
        $reference = $this->serializer->reference($resource, $item) ?? ['id' => ''];
        $id = $reference['id'];
        $label = $reference['label'] ?? null;
        if (is_array($label)) {
            // A localized title: in the current locale, or the first one
            $localized = $label[$this->app->getLocale()] ?? null;
            $label = is_string($localized) && $localized !== '' ? $localized : current(array_filter($label, fn (mixed $value): bool => is_string($value) && $value !== ''));
        }

        return new ContextOption($id, is_string($label) || is_int($label) ? (string) $label : $id, item: $item);
    }

    /**
     * @return list<ContextOption>
     */
    private function inlineOptions(ContextParam $param, Context $before, ?Authenticatable $user): array
    {
        $options = $param->inlineOptions();
        if ($options instanceof Closure) {
            $options = $this->within($before, fn (): mixed => $this->call($options, $before, $user));
        }
        if (! is_array($options)) {
            throw new LogicException(sprintf('The options() of the context param "%s" give %s instead of an array.', $param->name(), get_debug_type($options)));
        }

        return ContextOption::list($options, $param->name());
    }

    private function fixedValue(ContextParam $param, Context $before, ?Authenticatable $user, Request $request): ?string
    {
        $fixed = $param->fixedCallback();
        if ($fixed === null) {
            return null;
        }
        $value = $this->within($before, fn (): mixed => $this->call($fixed, $before, $user, ['request' => $request, Request::class => $request]));
        if ($value !== null && ! is_string($value) && ! is_int($value)) {
            throw new LogicException(sprintf('The fixed() of the context param "%s" gives a %s: return a value or null.', $param->name(), get_debug_type($value)));
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function defaultValue(ContextParam $param, Context $before, ?Authenticatable $user): ?string
    {
        $default = $param->defaultCallback();
        if ($default === null) {
            return null;
        }
        $value = $this->within($before, fn (): mixed => $this->call($default, $before, $user));
        if ($value !== null && ! is_string($value) && ! is_int($value)) {
            throw new LogicException(sprintf('The default() of the context param "%s" gives a %s: return a value or null.', $param->name(), get_debug_type($value)));
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function resource(ContextParam $param): ?Resource
    {
        $reference = $param->resourceReference();
        if ($reference === null) {
            return null;
        }

        return $this->resources->resolve($reference)
            ?? throw new LogicException(sprintf('The context param "%s" names the resource %s, which is not registered.', $param->name(), json_encode($reference)));
    }

    /**
     * A closure of a param, through the container, with the user as `$user` and the context of
     * the params before as `$context` (or any parameter of type `Context`).
     *
     * @param  array<string, mixed>  $parameters
     */
    private function call(Closure $callback, Context $before, ?Authenticatable $user, array $parameters = []): mixed
    {
        return $this->container->call($callback, [...$parameters, 'user' => $user, 'context' => $before, Context::class => $before]);
    }

    /**
     * Runs a callback while the current context has only the values before a param: what the
     * `scope()` of a resource reads.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function within(Context $before, Closure $callback): mixed
    {
        $current = $this->current();
        $saved = new Context;
        $saved->replace($current);
        $current->replace($before);
        try {
            return $callback();
        } finally {
            $current->replace($saved);
        }
    }

    private function current(): Context
    {
        return $this->container->make(Context::class);
    }

    /**
     * The choices whose label has `q`, ignoring case.
     *
     * @param  list<ContextOption>  $options
     * @return list<ContextOption>
     */
    private static function matching(array $options, string $q): array
    {
        if ($q === '') {
            return $options;
        }

        return array_values(array_filter($options, fn (ContextOption $option): bool => mb_stripos($option->label, $q) !== false));
    }
}
