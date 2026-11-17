<?php

namespace Panneau\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The data of a widget of a dashboard or of a summary, for `Widget::using()` (panneau-js
 * decisions 0064 and 0084). The class is built from the container for each request, so its
 * constructor can ask for the request, the current context (`Panneau\Context\Context`), and
 * where the widget is asked for (`Panneau\Dashboard\WidgetRequest`): its dashboard page, or the
 * summary of a resource with the query of its list, and the period of its overview.
 */
interface WidgetData
{
    /**
     * The data of the widget for the user, in the shape of its type, with its texts translated:
     * `['value' => 12, 'help' => '…', 'tone' => 'warning', 'url' => '…']` for a `stat`,
     * `['period' => 'day', 'points' => [['date' => '2026-10-05', 'value' => 4]], 'total' => 4]`
     * for a `series`, `['items' => [['title' => '…', 'url' => '…']], 'empty' => '…']` for a
     * `list`, `['items' => [['label' => '…', 'value' => 3, 'tone' => 'danger']], 'total' => 42]`
     * for a `breakdown`, or the shape of a plugin's widget. Data in the wrong shape is a `500`. Throw an
     * `AuthorizationException` for a `403`: the app hides the widget.
     *
     * @return array<string, mixed>
     */
    public function data(?Authenticatable $user): array;
}
