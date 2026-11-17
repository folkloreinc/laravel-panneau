<?php

namespace Panneau\Dashboard;

use Panneau\Query\ResourceQuery;

/**
 * Where and how the data of a widget is asked for, at `GET {api}/dashboard/widgets/{widget}`
 * (panneau-js decisions 0064 and 0084): on a dashboard page, or in the summary of a resource with
 * the query of its list.
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
     * @param  string|null  $page  The id of the dashboard page of the widget, `home` for the home
     *                             page, or null for a widget of a summary.
     * @param  string|null  $resource  The id of the resource whose summary holds the widget, or
     *                                 null for a widget of a dashboard page.
     * @param  ResourceQuery|null  $query  In a summary: the query of the list, as the list reads
     *                                     it (its filters and its search, in the scope of the
     *                                     resource, page 1 and the default sort), to give to its
     *                                     repository. Without filters or search for a widget with
     *                                     `followsList(false)`. Null on a dashboard page.
     */
    public function __construct(
        public string $widget,
        public ?string $page = Dashboard::HOME,
        public ?string $resource = null,
        public ?ResourceQuery $query = null,
    ) {}

    /**
     * The value of a filter of the list, as the list reads it, or null: in a summary only.
     */
    public function filter(string $id): mixed
    {
        return $this->query?->filter($id);
    }

    /**
     * The search of the list, or null: in a summary only.
     */
    public function search(): ?string
    {
        return $this->query?->search;
    }
}
