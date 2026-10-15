<?php

namespace Panneau\Query;

/**
 * One sort of a list: a field and a direction.
 */
final readonly class Sort
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::Ascending,
    ) {}

    /**
     * Parses one part of the `sort` parameter: `title`, or `-published_at` for descending.
     */
    public static function parse(string $sort): self
    {
        return str_starts_with($sort, '-')
            ? new self(substr($sort, 1), SortDirection::Descending)
            : new self($sort, SortDirection::Ascending);
    }

    /**
     * Parses the `sort` parameter: comma-separated sorts.
     *
     * @return list<self>
     */
    public static function parseList(string $sorts): array
    {
        $parts = array_filter(
            array_map(trim(...), explode(',', $sorts)),
            fn (string $part): bool => $part !== '' && $part !== '-',
        );

        return array_values(array_map(self::parse(...), $parts));
    }

    public function descending(): bool
    {
        return $this->direction === SortDirection::Descending;
    }

    public function toString(): string
    {
        return ($this->descending() ? '-' : '').$this->field;
    }
}
