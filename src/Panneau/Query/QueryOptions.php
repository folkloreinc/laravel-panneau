<?php

namespace Panneau\Query;

use Closure;

/**
 * What a resource's list declares, and so what the query string can ask for. Anything else
 * in the query string is ignored.
 */
final readonly class QueryOptions
{
    /**
     * Declared filters, keyed by id. Each one can have a normalizer, which receives the raw
     * value from the query string and returns the value given to the repository, or null to
     * ignore it.
     *
     * @var array<string, (Closure(mixed): mixed)|null>
     */
    public array $filters;

    /**
     * @param  array<string, (Closure(mixed): mixed)|null>|list<string>  $filters  Filter ids, or
     *                                                                             normalizers keyed by filter id.
     * @param  list<string>  $sorts  Fields the list can be sorted by.
     * @param  list<Sort>  $defaultSort  Sorts used when the query asks for none.
     * @param  list<string>  $searchFields  Fields searched by `q`. Empty: no search.
     * @param  list<string>|null  $fields  Fields that `fields` can ask for. Null: any.
     */
    public function __construct(
        array $filters = [],
        public array $sorts = [],
        public array $defaultSort = [],
        public array $searchFields = [],
        public ?array $fields = null,
        public int $perPage = 20,
        public int $maxPerPage = 100,
    ) {
        $normalized = [];
        foreach ($filters as $key => $value) {
            if (is_string($value)) {
                $normalized[$value] = null;
            } else {
                $normalized[(string) $key] = $value;
            }
        }
        $this->filters = $normalized;
    }
}
