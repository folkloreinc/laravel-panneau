<?php

namespace Panneau\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The data of a widget of the dashboard, for `Widget::using()` (panneau-js decision 0064). The
 * class is built from the container for each request, so its constructor can ask for the
 * request or the current context (`Panneau\Context\Context`).
 */
interface WidgetData
{
    /**
     * The data of the widget for the user, in the shape of its type, with its texts translated:
     * `['value' => 12, 'help' => '…', 'tone' => 'warning', 'url' => '…']` for a `stat`,
     * `['period' => 'day', 'points' => [['date' => '2026-10-05', 'value' => 4]], 'total' => 4]`
     * for a `series`, `['items' => [['title' => '…', 'url' => '…']], 'empty' => '…']` for a
     * `list`, or the shape of a plugin's widget. Data in the wrong shape is a `500`. Throw an
     * `AuthorizationException` for a `403`: the app hides the widget.
     *
     * @return array<string, mixed>
     */
    public function data(?Authenticatable $user): array;
}
