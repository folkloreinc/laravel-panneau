<?php

namespace Panneau\Resources;

use Closure;
use WeakMap;

/**
 * The fields and types of the resources, built once per request (#234): Panneau reads them many
 * times per request, for the definition, each item and each check. Scoped to the request, by
 * locale, since labels are translated. Flushed when a request is handled, when a user is
 * authenticated and when the context is resolved, which fields can depend on.
 *
 * @internal
 */
final class FieldCache
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
}
