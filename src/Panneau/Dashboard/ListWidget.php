<?php

namespace Panneau\Dashboard;

use Panneau\Dashboard\Concerns\FormatsNumbers;
use Panneau\Dashboard\Concerns\ReadsAResource;

/**
 * The builder of a `list` widget, from `Widget::list()`. A few items: the first of the list of a
 * resource (`resource()`), or data of its own (`using()`).
 */
final class ListWidget extends Widget
{
    use FormatsNumbers, ReadsAResource;
}
