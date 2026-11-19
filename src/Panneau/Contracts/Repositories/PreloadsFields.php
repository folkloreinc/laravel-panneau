<?php

namespace Panneau\Contracts\Repositories;

use Panneau\Fields\Field;

/**
 * A repository that loads at once what fields read from its items, such as the related items
 * of their relations, before they are serialized. A list of items then reads its values
 * without a query for each item. Without it, each item loads what its fields read, if anything.
 */
interface PreloadsFields
{
    /**
     * Loads, for every item at once, what the fields read from them. Items keep what they
     * already loaded. Fields it can't load ahead are left to be read item by item.
     *
     * @param  list<object>  $items  Items of this repository.
     * @param  list<Field>  $fields  The fields about to be read from the items.
     */
    public function preloadFields(array $items, array $fields): void;
}
