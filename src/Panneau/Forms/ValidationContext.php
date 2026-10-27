<?php

namespace Panneau\Forms;

use Closure;
use Panneau\Uploads\Upload;

/**
 * What field rules depend on: create or update, the content locales, a partial update, the
 * item being updated.
 */
final readonly class ValidationContext
{
    /**
     * @param  list<string>  $locales  Locales of localized fields.
     * @param  bool  $partial  Only the fields present in the values are validated (`PATCH`).
     * @param  object|null  $item  The item being updated.
     * @param  (Closure(string, string): bool)|null  $relationExists  Whether an id exists in a
     *                                                                resource, for relation fields.
     * @param  (Closure(mixed): ?Upload)|null  $uploads  Resolves an upload reference
     *                                                   (`{ "upload": "upl_…" }`) for the user,
     *                                                   for file fields.
     * @param  (Closure(string|null, string): (array<string, mixed>|null))|null  $media  The
     *                                                                                   media of an id in a library (by id or class, or
     *                                                                                   null for the only library), within its scope, for
     *                                                                                   media fields: the protocol's `MediaInfo`, or null.
     * @param  (Closure(string, string, ?string, ?Closure): bool)|null  $valueExists  Whether
     *                                                                                another item of the resource, within its scope, has a value
     *                                                                                of a field at the top of the resource (name, value, locale),
     *                                                                                for `Slug::unique()`. The item being updated doesn't count.
     *                                                                                The closure narrows the query of an `EloquentRepository`.
     *                                                                                Null in nested values and actions: nothing is checked.
     * @param  (Closure(list<string>): list<string>)|null  $libraries  The ids of the libraries
     *                                                                 a media field allows, from
     *                                                                 those it names (by id or
     *                                                                 class): those, in that
     *                                                                 order, or every library
     *                                                                 when it names none.
     */
    public function __construct(
        public FormMode $mode = FormMode::Create,
        public array $locales = [],
        public bool $partial = false,
        public ?object $item = null,
        public ?Closure $relationExists = null,
        public ?Closure $uploads = null,
        public ?Closure $media = null,
        public ?Closure $valueExists = null,
        public ?Closure $libraries = null,
    ) {}

    /**
     * The same context, for nested values: groups and repeater blocks are never partial, and
     * their values are never unique within the resource.
     */
    public function nested(): self
    {
        return new self($this->mode, $this->locales, false, $this->item, $this->relationExists, $this->uploads, $this->media, libraries: $this->libraries);
    }
}
