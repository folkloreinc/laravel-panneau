<?php

namespace TestApp\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Contracts\WidgetData;

/**
 * A `stat` with data of its own, for the tests of the dashboard: how many posts are overdue.
 */
class OverdueWidget implements WidgetData
{
    public static int $calls = 0;

    public function data(?Authenticatable $user): array
    {
        self::$calls++;

        return ['value' => 3, 'help' => __('Since Monday'), 'tone' => 'danger', 'url' => '/panneau/resources/posts'];
    }
}
