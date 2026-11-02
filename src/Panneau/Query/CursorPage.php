<?php

namespace Panneau\Query;

/**
 * A page of the items of a list paged with cursors (`CursorPaginated`): no page number, and no
 * total unless the repository counts.
 */
final readonly class CursorPage
{
    /**
     * @param  list<object>  $items
     * @param  string|null  $nextCursor  The cursor of the next page, null on the last page.
     * @param  string|null  $previousCursor  The cursor of the previous page, null when it is
     *                                       the first page, which has none (and on the first
     *                                       page).
     * @param  int|null  $total  The number of matching items, when the repository counts them.
     */
    public function __construct(
        public array $items,
        public int $perPage,
        public ?string $nextCursor = null,
        public ?string $previousCursor = null,
        public ?int $total = null,
    ) {}

    /**
     * The `meta` of the page in a list response, the protocol's `CursorPagination`.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'perPage' => $this->perPage,
            'nextCursor' => $this->nextCursor,
            'previousCursor' => $this->previousCursor,
            ...($this->total !== null ? ['total' => $this->total] : []),
        ];
    }
}
