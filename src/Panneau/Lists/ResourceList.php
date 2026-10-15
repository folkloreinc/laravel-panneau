<?php

namespace Panneau\Lists;

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
     * @var list<string>
     */
    private array $sorts = [];

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
     * Fields the list can be sorted by.
     *
     * @param  list<string>  $sorts
     */
    public function sorts(array $sorts): self
    {
        $this->sorts = $sorts;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSorts(): array
    {
        return $this->sorts;
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
     * What the query string can ask for.
     *
     * @param  bool  $searchable  Whether the repository supports search.
     * @param  list<string>|null  $fields  Fields that `fields` can ask for.
     */
    public function queryOptions(bool $searchable, ?array $fields, int $perPage, int $maxPerPage): QueryOptions
    {
        $filters = [];
        foreach ($this->filters as $filter) {
            $filters[$filter->id()] = $filter->normalize(...);
        }

        return new QueryOptions(
            filters: $filters,
            sorts: $this->sorts,
            defaultSort: $this->defaultSort !== null ? Sort::parseList($this->defaultSort) : [],
            searchFields: $searchable ? $this->search : [],
            fields: $fields,
            perPage: min($this->perPage ?? $perPage, $maxPerPage),
            maxPerPage: $maxPerPage,
        );
    }

    /**
     * The list in the protocol's definition.
     *
     * @param  bool  $searchable  Whether the repository supports search.
     * @return array<string, mixed>
     */
    public function toDefinition(bool $searchable): array
    {
        return array_filter([
            'columns' => $this->columns !== null
                ? array_map(fn (Column $column): array => $column->toDefinition($this->sorts), $this->columns)
                : null,
            'filters' => count($this->filters) > 0
                ? array_map(fn (Filter $filter): array => $filter->toDefinition(), $this->filters)
                : null,
            'sorts' => count($this->sorts) > 0 ? $this->sorts : null,
            'defaultSort' => $this->defaultSort,
            'search' => $searchable && count($this->search) > 0,
            'layouts' => $this->layouts,
            'perPage' => $this->perPage,
            'perPageOptions' => $this->perPageOptions,
            'inlineEdit' => count($this->inlineEdit) > 0 ? $this->inlineEdit : null,
            'selectable' => $this->selectable,
        ], fn (mixed $value): bool => $value !== null);
    }
}
