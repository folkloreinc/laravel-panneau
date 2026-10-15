<?php

namespace Panneau\Actions;

enum ActionPlacement: string
{
    /** On each list row. */
    case Row = 'row';

    /** On the list, for selections and resource actions. */
    case Toolbar = 'toolbar';

    /** In the item form header. */
    case Form = 'form';

    /** In an overflow menu. */
    case Menu = 'menu';
}
