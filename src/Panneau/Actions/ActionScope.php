<?php

namespace Panneau\Actions;

enum ActionScope: string
{
    /** One item, from a row or a form. */
    case Item = 'item';

    /** The selected items, or every item matching the list query. */
    case Selection = 'selection';

    /** No items, such as an import or an export of everything. */
    case Resource = 'resource';
}
