<?php

namespace Panneau\Query;

/**
 * What a repository's `query()` returns: a page of the items in a scope, matching ids,
 * exclusions, filters and search, in an order.
 *
 * Built from the protocol's list query string with `fromParameters()`, which keeps only what
 * the resource declares, or by Panneau itself, for example to resolve an action's selection.
 */
final readonly class ResourceQuery
{
    /**
     * Longest accepted search, in characters.
     */
    public const MAX_SEARCH_LENGTH = 255;

    /**
     * @param  list<Sort>  $sorts
     * @param  array<string, mixed>  $filters  Values keyed by filter id: a scalar, or a list of
     *                                         scalars.
     * @param  list<string>  $searchFields  Fields searched by `$search`.
     * @param  list<string>|null  $fields  Fields the client asked for. Null: every field.
     * @param  array<string, mixed>  $scope  The resource's scope, applied to every query.
     * @param  list<string>|null  $ids  Only these items. Null: no restriction.
     * @param  list<string>  $except  Never these items.
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public array $sorts = [],
        public array $filters = [],
        public ?string $search = null,
        public array $searchFields = [],
        public ?array $fields = null,
        public array $scope = [],
        public ?array $ids = null,
        public array $except = [],
    ) {}

    /**
     * Parses the protocol's list query string (`page`, `perPage`, `sort`, `filter[...]`, `q`,
     * `fields`). Filters, sorts and fields that the options don't declare are ignored, and so
     * are invalid values.
     *
     * @param  array<array-key, mixed>  $parameters  The query string, as parsed by PHP.
     * @param  array<string, mixed>  $scope
     */
    public static function fromParameters(
        array $parameters,
        QueryOptions $options,
        array $scope = [],
    ): self {
        $perPage = self::positiveInteger($parameters['perPage'] ?? null) ?? $options->perPage;

        return new self(
            page: self::positiveInteger($parameters['page'] ?? null) ?? 1,
            perPage: max(1, min($perPage, $options->maxPerPage)),
            sorts: self::parseSorts($parameters['sort'] ?? null, $options),
            filters: self::parseFilters($parameters['filter'] ?? null, $options),
            search: self::parseSearch($parameters['q'] ?? null, $options),
            searchFields: $options->searchFields,
            fields: self::parseFields($parameters['fields'] ?? null, $options),
            scope: $scope,
        );
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function withScope(array $scope): self
    {
        return $this->with(['scope' => $scope]);
    }

    /**
     * @param  list<string>|null  $ids
     */
    public function withIds(?array $ids): self
    {
        return $this->with(['ids' => $ids === null ? null : array_values(array_unique($ids))]);
    }

    /**
     * @param  list<string>  $except
     */
    public function withExcept(array $except): self
    {
        return $this->with(['except' => array_values(array_unique($except))]);
    }

    public function withPage(int $page, ?int $perPage = null): self
    {
        return $this->with(['page' => max(1, $page), 'perPage' => max(1, $perPage ?? $this->perPage)]);
    }

    /**
     * @param  list<Sort>  $sorts
     */
    public function withSorts(array $sorts): self
    {
        return $this->with(['sorts' => $sorts]);
    }

    /**
     * The value of a filter, or null when the query doesn't use it.
     */
    public function filter(string $id): mixed
    {
        return $this->filters[$id] ?? null;
    }

    /**
     * Whether the query asked for a field. Always true when it didn't restrict its fields.
     */
    public function wantsField(string $field): bool
    {
        return $this->fields === null || in_array($field, $this->fields, true);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function with(array $changes): self
    {
        return new self(...array_merge([
            'page' => $this->page,
            'perPage' => $this->perPage,
            'sorts' => $this->sorts,
            'filters' => $this->filters,
            'search' => $this->search,
            'searchFields' => $this->searchFields,
            'fields' => $this->fields,
            'scope' => $this->scope,
            'ids' => $this->ids,
            'except' => $this->except,
        ], $changes));
    }

    private static function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }
        if (! is_string($value) || preg_match('/^\d{1,9}$/', $value) !== 1) {
            return null;
        }
        $integer = (int) $value;

        return $integer >= 1 ? $integer : null;
    }

    /**
     * @return list<Sort>
     */
    private static function parseSorts(mixed $value, QueryOptions $options): array
    {
        $sorts = [];
        if (is_string($value)) {
            foreach (Sort::parseList($value) as $sort) {
                if (in_array($sort->field, $options->sorts, true) && ! isset($sorts[$sort->field])) {
                    $sorts[$sort->field] = $sort;
                }
            }
        }

        return count($sorts) > 0 ? array_values($sorts) : $options->defaultSort;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseFilters(mixed $value, QueryOptions $options): array
    {
        if (! is_array($value)) {
            return [];
        }

        $filters = [];
        foreach ($options->filters as $id => $normalizer) {
            if (! array_key_exists($id, $value)) {
                continue;
            }
            $raw = self::filterValue($value[$id]);
            if ($raw === null) {
                continue;
            }
            $normalized = $normalizer !== null ? $normalizer($raw) : $raw;
            if ($normalized !== null) {
                $filters[$id] = $normalized;
            }
        }

        return $filters;
    }

    /**
     * Keeps scalars and lists of scalars; anything else, and empty values, are null.
     */
    private static function filterValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $item) {
                if (is_scalar($item) && $item !== '') {
                    $values[] = $item;
                }
            }

            return count($values) > 0 ? $values : null;
        }

        return is_scalar($value) && $value !== '' ? $value : null;
    }

    private static function parseSearch(mixed $value, QueryOptions $options): ?string
    {
        if (! is_string($value) || count($options->searchFields) === 0) {
            return null;
        }
        $search = trim(mb_substr($value, 0, self::MAX_SEARCH_LENGTH));

        return $search !== '' ? $search : null;
    }

    /**
     * @return list<string>|null
     */
    private static function parseFields(mixed $value, QueryOptions $options): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $fields = array_values(array_unique(array_filter(
            array_map(trim(...), explode(',', $value)),
            fn (string $field): bool => $field !== ''
                && ($options->fields === null || in_array($field, $options->fields, true)),
        )));

        return $fields;
    }
}
