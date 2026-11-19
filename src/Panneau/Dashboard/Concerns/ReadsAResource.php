<?php

namespace Panneau\Dashboard\Concerns;

/**
 * A built-in widget that reads the list of a resource, or its aggregates. See
 * `Widget::resource()`.
 *
 * @internal
 */
trait ReadsAResource
{
    /**
     * @param  array<string, mixed>  $query  Scalars, or lists of scalars.
     */
    public function resource(string $resource, array $query = []): static
    {
        return parent::resource($resource, $query);
    }
}
