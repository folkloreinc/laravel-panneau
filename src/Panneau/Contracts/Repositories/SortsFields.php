<?php

namespace Panneau\Contracts\Repositories;

use Panneau\Fields\Field;

/**
 * A repository that tells which fields it can sort by, for the lists that don't declare their
 * sorts: those sort by the fields of their columns that `Field::isSortable()` allows and this
 * repository too. Without it, the repository sorts by all of them.
 */
interface SortsFields
{
    public function sortsBy(Field $field): bool;
}
