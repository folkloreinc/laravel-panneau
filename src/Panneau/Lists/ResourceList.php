<?php

namespace Panneau\Lists;

use Panneau\Fields\Field;
use Panneau\Lists\Filters\Filter;
use Panneau\Query\QueryOptions;
use Panneau\Query\Sort;

/**
 * The list of a resource: its columns, filters, sorts and search. What it declares is also
 * all the list's query string can ask for.
 */
final class ResourceList
{
    /**
     * @var list<Column>|null
     */
    private ?array $columns = null;

    /**
     * @var list<Filter>
     */
    private array $filters = [];

    /**
     * @var list<string>|null
     */
    private ?array $sorts = null;

    /**
     * The fields the list can sort by when it doesn't declare its sorts.
     *
     * @var list<Field>
     */
    private array $sortableFields = [];

    private ?string $defaultSort = null;

    /**
     * @var list<string>
     */
    private array $search = [];

    /**
     * @var list<string>|null
     */
    private ?array $layouts = null;

    private ?int $perPage = null;

    /**
     * @var list<int>|null
     */
    private ?array $perPageOptions = null;

    /**
     * @var list<string>
     */
    private array $inlineEdit = [];

    private ?bool $selectable = null;

    private bool $cursorPagination = false;

    /**
     * @var array{field: string, filter: string|null, perPage: int|null}|null
     */
    private ?array $calendar = null;

    /**
     * @param  list<string|Column>  $columns  Field names, or columns.
     */
    public function columns(array $columns): self
    {
        $this->columns = array_map(
            fn (string|Column $column): Column => is_string($column) ? Column::make($column) : $column,
            $columns,
        );

        return $this;
    }

    /**
     * @return list<Column>|null
     */
    public function getColumns(): ?array
    {
        return $this->columns;
    }

    /**
     * @param  list<Filter>  $filters
     */
    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return list<Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Fields the list can be sorted by. Without it, the list sorts by the fields of its columns
     * whose values sort as they are stored (`Field::isSortable()`), when its repository can sort
     * by them; `sorts([])` turns sorting off.
     *
     * @param  list<string>  $sorts
     */
    public function sorts(array $sorts): self
    {
        $this->sorts = $sorts;

        return $this;
    }

    /**
     * The fields the list can sort by when it doesn't declare its sorts: those of the resource
     * that its repository can sort by. The registry gives them.
     *
     * @internal
     *
     * @param  list<Field>  $fields
     */
    public function sortableFields(array $fields): self
    {
        $this->sortableFields = $fields;

        return $this;
    }

    /**
     * The fields the list can be sorted by: those of `sorts()`, or without it, the sortable
     * fields of its columns (every sortable field without columns, since the default columns are
     * among them), but those of a column marked `sortable(false)`. A column marked `sortable()`
     * adds its field.
     *
     * @return list<string>
     */
    public function getSorts(): array
    {
        $columns = $this->columns ?? [];
        $sorts = $this->sorts;
        if ($sorts === null) {
            $shown = $this->columns !== null
                ? array_map(fn (Column $column): ?string => $column->field(), $columns)
                : null;
            $unsortable = array_map(
                fn (Column $column): ?string => $column->field(),
                array_filter($columns, fn (Column $column): bool => $column->getSortable() === false),
            );
            $sorts = [];
            foreach ($this->sortableFields as $field) {
                $name = $field->name();
                if ($field->isSortable() && ! in_array($name, $unsortable, true)
                    && ($shown === null || in_array($name, $shown, true))) {
                    $sorts[] = $name;
                }
            }
        }
        foreach ($columns as $column) {
            $field = $column->field();
            if ($column->getSortable() === true && $field !== null && ! in_array($field, $sorts, true)) {
                $sorts[] = $field;
            }
        }

        return $sorts;
    }

    /**
     * Same format as the `sort` query parameter: `-published_at,title`.
     */
    public function defaultSort(string $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    /**
     * Fields searched by the `q` parameter.
     *
     * @param  list<string>  $fields
     */
    public function search(array $fields): self
    {
        $this->search = $fields;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSearch(): array
    {
        return $this->search;
    }

    /**
     * Layouts the list can use: `table`, `cards`, `gallery`, `calendar`, or a plugin's.
     *
     * @param  list<string>  $layouts
     */
    public function layouts(array $layouts): self
    {
        $this->layouts = $layouts;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = max(1, $perPage);

        return $this;
    }

    /**
     * @param  list<int>  $options
     */
    public function perPageOptions(array $options): self
    {
        $this->perPageOptions = $options;

        return $this;
    }

    /**
     * Fields editable in the list.
     *
     * @param  list<string>  $fields
     */
    public function inlineEdit(array $fields): self
    {
        $this->inlineEdit = $fields;

        return $this;
    }

    public function selectable(bool $selectable = true): self
    {
        $this->selectable = $selectable;

        return $this;
    }

    /**
     * Pages the list with cursors rather than page numbers (`pagination: "cursor"`), for lists
     * too large to count or too live for page numbers: thousands of jobs a day, newest first.
     * Its pages have no number and no total. Only when the repository is `CursorPaginated`:
     * without it, the list keeps numbered pages.
     */
    public function cursorPagination(bool $cursorPagination = true): self
    {
        $this->cursorPagination = $cursorPagination;

        return $this;
    }

    public function paginatesWithCursors(): bool
    {
        return $this->cursorPagination;
    }

    /**
     * Shows the items on the days of a date field, in the `calendar` layout (panneau-js
     * decision 0044). The calendar asks for a month through the list's `date-range` filter on
     * that field: `$filter`, the field's name by default, which the list must declare. A month
     * takes one request of `$perPage` items (100 by default, within `panneau.list.max_per_page`).
     */
    public function calendar(string $field, ?string $filter = null, ?int $perPage = null): self
    {
        $this->calendar = ['field' => $field, 'filter' => $filter, 'perPage' => $perPage !== null ? max(1, $perPage) : null];

        return $this;
    }

    /**
     * What the query string can ask for.
     *
     * @param  bool  $searchable  Whether the repository supports search.
     * @param  list<string>|null  $fields  Fields that `fields` can ask for.
     * @param  bool  $cursors  Whether the list is paged with cursors: it asks for it, and the
     *                         repository can.
     */
    public function queryOptions(bool $searchable, ?array $fields, int $perPage, int $maxPerPage, bool $cursors = false): QueryOptions
    {
        $filters = [];
        foreach ($this->filters as $filter) {
            $filters[$filter->id()] = $filter->normalize(...);
        }

        return new QueryOptions(
            filters: $filters,
            sorts: $this->getSorts(),
            defaultSort: $this->defaultSort !== null ? Sort::parseList($this->defaultSort) : [],
            searchFields: $searchable ? $this->search : [],
            fields: $fields,
            perPage: min($this->perPage ?? $perPage, $maxPerPage),
            maxPerPage: $maxPerPage,
            cursorPagination: $cursors,
        );
    }

    /**
     * The list in the protocol's definition.
     *
     * @param  bool  $searchable  Whether the repository supports search.
     * @param  bool  $cursors  Whether the list is paged with cursors: it asks for it, and the
     *                         repository can.
     * @return array<string, mixed>
     */
    public function toDefinition(bool $searchable, bool $cursors = false): array
    {
        $sorts = $this->getSorts();

        return array_filter([
            'columns' => $this->columns !== null
                ? array_map(fn (Column $column): array => $column->toDefinition($sorts), $this->columns)
                : null,
            'filters' => count($this->filters) > 0
                ? array_map(fn (Filter $filter): array => $filter->toDefinition(), $this->filters)
                : null,
            'sorts' => count($sorts) > 0 ? $sorts : null,
            'defaultSort' => $this->defaultSort,
            'search' => $searchable && count($this->search) > 0,
            'layouts' => $this->layouts,
            'perPage' => $this->perPage,
            'perPageOptions' => $this->perPageOptions,
            'pagination' => $cursors ? 'cursor' : null,
            'inlineEdit' => count($this->inlineEdit) > 0 ? $this->inlineEdit : null,
            'selectable' => $this->selectable,
            'calendar' => $this->calendar !== null
                ? array_filter($this->calendar, fn (mixed $value): bool => $value !== null)
                : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
