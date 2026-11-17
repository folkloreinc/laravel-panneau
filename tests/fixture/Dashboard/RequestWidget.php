<?php

namespace TestApp\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Contracts\WidgetData;
use Panneau\Dashboard\WidgetRequest;

/**
 * A `stat` with data of its own that gets the request of the widget in its constructor, for the
 * tests of the dashboard pages (panneau-js decision 0084): its help says where it was asked for.
 */
class RequestWidget implements WidgetData
{
    public function __construct(private readonly WidgetRequest $request) {}

    public function data(?Authenticatable $user): array
    {
        return ['value' => 1, 'help' => $this->request->widget.' on '.$this->request->page];
    }
}
