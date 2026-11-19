<?php

namespace Panneau\Resources;

use Closure;
use Illuminate\Container\Container;
use WeakMap;

/**
 * What Panneau derives from the resources, built once per request (#245): their fields and
 * types (#234), lists and actions, what the `Authorizer` checks once per resource (#244), the
 * roles of users, and the definitions of block types (#246). Panneau reads them many times per
 * request, for the definition, each item and each check. Scoped to the request, by locale, since
 * labels are translated. Flushed when a request is handled, when a user is authenticated and when
 * the context is resolved, which they can depend on: a long-lived process (Octane, a queue
 * worker) builds them again for each request. What only depends on the config, such as the
 * repository of a resource, is kept by the `ResourceRegistry` for the life of the process.
 *
 * @internal
 */
final class RequestCache
{
    /**
     * @var WeakMap<object, array<string, mixed>>
     */
    private WeakMap $values;

    /**
     * @param  Closure(): string  $locale
     */
    public function __construct(private readonly Closure $locale)
    {
        $this->values = new WeakMap;
    }

    /**
     * The value of a key of an owner, built by `$build` the first time.
     *
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public function remember(object $owner, string $key, Closure $build): mixed
    {
        $key = ($this->locale)().'|'.$key;
        if (isset($this->values[$owner]) && array_key_exists($key, $this->values[$owner])) {
            return $this->values[$owner][$key];
        }
        $value = $build();
        // Read again: building can remember other keys of the same owner
        $this->values[$owner] = [...$this->values[$owner] ?? [], $key => $value];

        return $value;
    }

    public function forget(object $owner): void
    {
        unset($this->values[$owner]);
    }

    public function flush(): void
    {
        $this->values = new WeakMap;
    }

    /**
     * `remember()` in the cache of the current request, for objects that aren't resolved from
     * the container, such as the definition of a block type (#246). Outside an app, such as a
     * field built in a unit test, `$build` runs each time.
     *
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function rememberInRequest(object $owner, string $key, Closure $build): mixed
    {
        $container = Container::getInstance();
        if (! $container->bound(self::class)) {
            return $build();
        }

        return $container->make(self::class)->remember($owner, $key, $build);
    }

    /**
     * `forget()` in the cache of the current request, when it has been built.
     */
    public static function forgetInRequest(object $owner): void
    {
        $container = Container::getInstance();
        if ($container->resolved(self::class)) {
            $container->make(self::class)->forget($owner);
        }
    }
}
