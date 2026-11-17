<?php

namespace Panneau\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The summary of a resource (panneau-js decision 0084): widgets drawn above its list, in the grid
 * of a dashboard, which follow its filters and its search. `Resource::summary()` returns its
 * widgets, or this builder for its options:
 *
 * ```php
 * public function summary(): Summary
 * {
 *     return Summary::make([
 *         Widget::stat('failed')->resource(self::class, ['filter[status]' => 'failed']),
 *         Widget::series('per-day')->resource(self::class)->per('day', 'sent_at'),
 *     ])->listCollapsed();
 * }
 * ```
 */
final class Summary
{
    private bool $listCollapsed = false;

    private ?Period $period = null;

    /**
     * @param  list<Widget>|Closure  $widgets
     */
    private function __construct(private readonly array|Closure $widgets) {}

    /**
     * The widgets of the summary, in order: `Widget` builders, or a closure called through the
     * container with the user as `$user`, which returns them. Each user gets those whose ability
     * they have (`Widget::can()`).
     *
     * @param  list<Widget>|(Closure(?Authenticatable): list<Widget>)  $widgets
     */
    public static function make(array|Closure $widgets): self
    {
        return new self($widgets);
    }

    /**
     * The resource opens on its summary, with its list folded under it behind "Show the N
     * items" (`summary.list: "collapsed"`): a log read through its charts first. A URL that asks
     * for some items (filters, a search, a page) shows the list at once.
     */
    public function listCollapsed(bool $collapsed = true): self
    {
        $this->listCollapsed = $collapsed;

        return $this;
    }

    /**
     * One period for the summary (panneau-js decision 0084), chosen in its header among
     * `$options` (`7d`, `30d`, `90d`, `month`, `year`), `$default` or the first one when it opens.
     * It is the list's own `date-range` filter `$filter`, the first one of the list without it:
     * choosing it filters the rows, and the widgets that follow the list. The widgets with data
     * of their own also get its days in their `WidgetRequest`, unless they have
     * `Widget::period(false)`.
     *
     * @param  list<string>  $options
     */
    public function period(array $options, ?string $default = null, ?string $filter = null): self
    {
        $this->period = new Period($options, $default, $filter);

        return $this;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getPeriod(): ?Period
    {
        return $this->period;
    }

    /**
     * @internal Read by `Dashboard`.
     *
     * @return list<Widget>|Closure
     */
    public function getWidgets(): array|Closure
    {
        return $this->widgets;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function collapsesList(): bool
    {
        return $this->listCollapsed;
    }
}
