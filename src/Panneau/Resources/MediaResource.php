<?php

namespace Panneau\Resources;

use Illuminate\Http\Request;
use LogicException;
use Panneau\Fields\File;
use Panneau\Lists\Filters\MediaTypeFilter;
use Panneau\Lists\ResourceList;
use Panneau\Media\MediaTransform;
use Panneau\Media\MediaTypes;

/**
 * A media library: a resource of kind `media` (panneau-js decision 0026).
 *
 * - **Its `file` field** holds the original, one file. Panneau adds it before the fields of
 *   `fields()`, which must not declare it: configure it with `file()`. Its `accept` defaults to
 *   the MIME types of `mediaTypes()`.
 * - **Its items** have a read-only `media`, from `media()`: type, MIME type, size, URLs,
 *   dimensions, status, and the media's own transform, from `mediaTransform()`: the value of
 *   its `transform` field when it declares one.
 * - **Its list** has a `type` filter, added to the filters of `list()`.
 * - **Its scope** is `scope()`, plus the types of `mediaTypes()` under the `type` key: lists,
 *   items and `media` fields only reach media of those types, and a media of another type
 *   can't be made.
 *
 * Without `laravel-mediatheque`, a library is an Eloquent model with a JSON `file` column and a
 * `type` column, returned by `model()`: Panneau stores the file with the `file` field, and
 * fills `type` from its MIME type. `MediathequeResource` is a library on `laravel-mediatheque`.
 */
abstract class MediaResource extends Resource
{
    /**
     * The name of the field that holds the original.
     */
    public const FILE = 'file';

    /**
     * The key of the items' media, and of the media types in the scope.
     */
    public const MEDIA = 'media';

    public const TYPE = 'type';

    /**
     * The name of the field of the media's own transform, when the library declares one.
     */
    public const TRANSFORM = 'transform';

    private ?File $fileField = null;

    final public function kind(): string
    {
        return 'media';
    }

    /**
     * The types of media the library holds: `image`, `video`, `audio`, `document`, `font`,
     * `other`, or a type of the backend. Empty: every type.
     *
     * @return list<string>
     */
    public function mediaTypes(): array
    {
        return [];
    }

    /**
     * Configures the `file` field: `accept()`, `maxSize()`, `required()`, the disk… It is
     * required by default.
     */
    protected function file(File $file): File
    {
        return $file;
    }

    /**
     * The field of the original, configured by `file()`.
     */
    final public function fileField(): File
    {
        if ($this->fileField !== null) {
            return $this->fileField;
        }
        $field = $this->file($this->newFileField()->required());
        if ($field->name() !== self::FILE || $field->isMultiple()) {
            throw new LogicException(sprintf('The file field of the library %s must be one file named "%s".', static::class, self::FILE));
        }
        if (! $field->hasAccept()) {
            $accept = MediaTypes::accept($this->mediaTypes(), $this->mimePatterns(...));
            if ($accept !== null) {
                $field->accept($accept);
            }
        }

        return $this->fileField = $field;
    }

    /**
     * The field of the original, before `file()` configures it.
     *
     * @internal
     */
    protected function newFileField(): File
    {
        return File::make(self::FILE);
    }

    /**
     * The `file` field, then the fields of `fields()`.
     *
     * @internal Declare fields in `fields()`, and configure the file with `file()`.
     */
    public function allFields(): array
    {
        $fields = $this->fields();
        foreach ($fields as $field) {
            if (in_array($field->name(), [self::FILE, self::MEDIA], true)) {
                throw new LogicException(sprintf(
                    'The library %s declares a field "%s": %s.',
                    static::class,
                    $field->name(),
                    $field->name() === self::FILE ? 'Panneau adds it, configure it with file()' : 'it is the media of the items',
                ));
            }
        }

        return [$this->fileField(), ...$fields];
    }

    /**
     * `scope()`, and the media types under the `type` key.
     *
     * @internal Override `scope()`.
     */
    public function repositoryScope(Request $request): array
    {
        $scope = $this->scope($request);
        $types = $this->mediaTypes();
        if (count($types) > 0) {
            $scope[self::TYPE] = array_values(array_unique($types));
        }

        return $scope;
    }

    /**
     * The list of `list()`, with the `type` filter first, unless it declares one.
     *
     * @internal Override `list()`.
     */
    public function makeList(): ResourceList
    {
        $list = parent::makeList();
        foreach ($list->getFilters() as $filter) {
            if ($filter->id() === self::TYPE) {
                return $list;
            }
        }
        $types = count($this->mediaTypes()) > 0 ? $this->mediaTypes() : MediaTypes::ALL;
        $options = [];
        foreach ($types as $type) {
            $options[$type] = MediaTypes::label($type);
        }

        return $list->filters([
            MediaTypeFilter::make(self::TYPE)->label(__('Type'))->options($options)->multiple(),
            ...$list->getFilters(),
        ]);
    }

    /**
     * The media of an item, the protocol's `MediaInfo`: what the backend computed about its
     * file. By default, from the stored file of the `file` field: its type (the item's `type`,
     * or its MIME type's), MIME type, size and URL, the same URL as thumbnail for an image, and
     * its own transform (`mediaTransform()`) when it has one.
     *
     * @return array<string, mixed>
     */
    public function media(object $item): array
    {
        $field = $this->fileField();
        $stored = File::storedFiles(data_get($item, self::FILE))[0] ?? null;
        $type = data_get($item, self::TYPE);
        $type = is_string($type) && $type !== '' ? $type : MediaTypes::fromMime($stored['mime'] ?? null);
        $url = $stored !== null ? $field->url($stored) : null;

        $media = [
            'type' => $type,
            'mime' => $stored['mime'] ?? null,
            'size' => $stored['size'] ?? null,
            'url' => $url,
            'thumbnailUrl' => $type === MediaTypes::IMAGE ? $url : null,
        ];
        $transform = $this->mediaTransform($item);
        if ($transform !== null) {
            $media['transform'] = $transform;
        }

        return $media;
    }

    /**
     * The media's own transform (the protocol's `MediaTransform`), which the references of
     * `media` and `image` fields give, so that a transform for one use can be made over it
     * (panneau-js decision 0031). By default, the value of the library's `transform` field,
     * when it declares one. Null without a transform, or with an invalid one.
     *
     * @return array<string, mixed>|null
     */
    public function mediaTransform(object $item): ?array
    {
        return MediaTransform::normalize($this->field(self::TRANSFORM)?->resolve($item));
    }

    /**
     * The label of an item in `media` values: the value of its `itemTitle`, in the current
     * locale for a localized field, or else the name of its file.
     */
    public function mediaLabel(object $item): ?string
    {
        $title = $this->itemTitle();
        $field = $title !== null ? $this->field($title) : null;
        $value = $field?->resolve($item);
        if (is_array($value)) {
            $value = $value[app()->getLocale()] ?? collect($value)->first(fn (mixed $localized): bool => is_string($localized) && $localized !== '');
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }
        $file = $this->fileField()->resolve($item);
        $name = is_array($file) ? ($file['name'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The MIME types of a media type, as `accept` patterns, for the default `accept` of the
     * file. Null when they can't be listed: the file then accepts any type, and the backend
     * refuses the files of other types when it saves them.
     *
     * @return list<string>|null
     */
    protected function mimePatterns(string $type): ?array
    {
        return MediaTypes::mimePatterns($type);
    }
}
