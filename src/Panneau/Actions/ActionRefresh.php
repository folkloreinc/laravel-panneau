<?php

namespace Panneau\Actions;

enum ActionRefresh: string
{
    case None = 'none';

    /** Refetch the affected items. */
    case Items = 'items';

    /** Refetch the list and the counts. */
    case List = 'list';
}
