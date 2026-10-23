<?php

namespace Panneau\Contracts\Repositories;

/**
 * A repository that says whether a slug is taken, for the `Slug` fields with `unique()`.
 *
 * `EloquentRepository` doesn't need it: it queries the field's column (see `slugQuery()`).
 * Other repositories, such as the adapter of Folklore entities, implement it to support
 * `unique()`. When a repository implements it, it answers every lookup, and the closure given
 * to `unique()`, which narrows an Eloquent query, is not used: scope the lookup with `$values`
 * instead (a page's parent).
 */
interface ChecksSlugs
{
    /**
     * Whether an item other than `$exceptId`, within the scope, has this value for the field.
     *
     * @param  string  $field  The name of the field, at the top of the resource.
     * @param  string|null  $locale  The locale of the value, for a localized field. Null otherwise.
     * @param  string|null  $exceptId  The public id of the item being edited, or null when creating one.
     * @param  array<string, mixed>  $scope  The resource's scope, as for `query()`.
     * @param  array<string, mixed>  $values  The form's values, keyed by field name, as they would be
     *                                        saved (relations as ids). They come from the client and
     *                                        are not validated: use them to scope the lookup only.
     */
    public function slugExists(string $field, string $value, ?string $locale, ?string $exceptId, array $scope, array $values): bool;
}
