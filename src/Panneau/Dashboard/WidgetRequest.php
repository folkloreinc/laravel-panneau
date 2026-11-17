<?php

namespace Panneau\Dashboard;

/**
 * Where and how the data of a widget is asked for, at `GET {api}/dashboard/widgets/{widget}`
 * (panneau-js decisions 0064 and 0084): the dashboard page of the widget.
 *
 * The data of a widget gets it from the container, for the time of the request: a `WidgetData`
 * class asks for it in its constructor, and a closure of `Widget::using()` as a parameter of
 * this type (`fn (?Authenticatable $user, WidgetRequest $request)`). `WidgetData::data()` keeps
 * its signature.
 */
final readonly class WidgetRequest
{
    /**
     * @param  string  $widget  The id of the widget.
     * @param  string  $page  The id of the dashboard page of the widget: `home` for the home
     *                        page.
     */
    public function __construct(
        public string $widget,
        public string $page = Dashboard::HOME,
    ) {}
}
