<?php

namespace Panneau\Media;

use Illuminate\Http\Request;
use Panneau\Fields\Media;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\MediaResource;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * Finds the media that `media` values reference, through the repository of their library and
 * within its scope for a request, and turns them into the protocol's `MediaReference`. The
 * library of a value is the `resource` it keeps, or the field's library when it allows one only.
 * Media are found once, and the media of a page of items in one query per library. It finds
 * the pages of `link` values the same way, in one query per resource (`item()`, `queue()`).
 *
 * @internal
 */
final class MediaReferences
{
    /**
     * The items found, media or pages, by resource id and item id. Null when an id is not in
     * the resource.
     *
     * @var array<string, array<string, object|null>>
     */
    private array $found = [];

    /**
     * The ids to find at once with `loadQueued()`, by resource id.
     *
     * @var array<string, list<string>>
     */
    private array $queued = [];

    /**
     * The resources of the queued ids, by id.
     *
     * @var array<string, \Panneau\Resources\Resource>
     */
    private array $queuedResources = [];

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Request $request,
    ) {}

    /**
     * The `MediaInfo` of a media of a library (by id or class, or null for the only library),
     * or null when the library has no such media in its scope.
     *
     * @return array<string, mixed>|null
     */
    public function media(?string $library, string $id): ?array
    {
        $resource = $this->resources->library($library);
        $item = $resource !== null ? $this->find($resource, $id) : null;

        return $resource !== null && $item !== null ? $resource->media($item) : null;
    }

    /**
     * A media of a library, within its scope.
     */
    public function find(MediaResource $library, string $id): ?object
    {
        return $this->item($library, $id);
    }

    /**
     * An item of a resource, such as the page of a link, within its scope for the request: found
     * once, with the ids queued for its resource.
     */
    public function item(Resource $resource, string $id): ?object
    {
        $this->queue($resource, $id);
        $this->loadQueued();

        return $this->found[$resource->id()][$id] ?? null;
    }

    /**
     * Queues the id of an item of a resource, which `loadQueued()` finds with the others.
     */
    public function queue(Resource $resource, string $id): void
    {
        $key = $resource->id();
        if (array_key_exists($id, $this->found[$key] ?? [])) {
            return;
        }
        $this->queuedResources[$key] = $resource;
        $this->queued[$key][] = $id;
    }

    /**
     * Queues the media of the value of a `media` field.
     */
    public function queueMedia(Media $field, mixed $value): void
    {
        $libraries = $this->libraries($field);
        foreach (self::entries($field, $value) as $entry) {
            $id = is_array($entry) ? Media::idOf($entry) : null;
            $library = is_array($entry) ? self::libraryOf($libraries, $entry) : null;
            if ($id !== null && $library !== null) {
                $this->queue($library, $id);
            }
        }
    }

    /**
     * Finds the queued items: one query for each resource.
     */
    public function loadQueued(): void
    {
        $queued = $this->queued;
        $resources = $this->queuedResources;
        $this->queued = [];
        $this->queuedResources = [];
        foreach ($queued as $key => $ids) {
            $this->load($resources[$key], $ids);
        }
    }

    /**
     * Finds the media of the top-level `media` fields of items at once.
     *
     * @param  iterable<object>  $items
     */
    public function preload(Resource $resource, iterable $items): void
    {
        $typed = $resource->hasTypes();
        // The fields of each type, in a resource with types
        $fieldsOf = [];
        foreach ($items as $item) {
            $type = $typed ? $resource->typeOf($item) : null;
            $key = $type ?? '';
            $fieldsOf[$key] ??= array_values(array_filter($resource->typeFields($type), fn ($field): bool => $field instanceof Media));
            foreach ($fieldsOf[$key] as $field) {
                $this->queueMedia($field, $field->resolve($item));
            }
        }
        $this->loadQueued();
    }

    /**
     * The value of a `media` field as references: `{ id, resource, label, media, transform }`, a
     * list of them, or an object of them keyed by locale. A media that isn't in its library
     * anymore keeps its id, library and transform.
     */
    public function references(Media $field, mixed $value): mixed
    {
        $libraries = $this->libraries($field);
        if ($field->isLocalized() && is_array($value) && ! array_is_list($value) && ! array_key_exists('id', $value)) {
            return array_map(fn (mixed $localized): mixed => $this->convert($field, $libraries, $localized), $value);
        }

        return $this->convert($field, $libraries, $value);
    }

    /**
     * A reference to a media item of a library.
     *
     * @return array{id: string, resource: string, label?: string, media: array<string, mixed>}
     */
    public function reference(MediaResource $library, object $item): array
    {
        $reference = ['id' => $this->resources->repository($library)->id($item), 'resource' => $library->id()];
        $label = $library->mediaLabel($item);
        if ($label !== null) {
            $reference['label'] = $label;
        }
        $reference['media'] = $library->media($item);

        return $reference;
    }

    /**
     * The libraries a field allows, by id.
     *
     * @return array<string, MediaResource>
     */
    private function libraries(Media $field): array
    {
        $libraries = [];
        foreach ($this->resources->mediaLibraries($field->libraries()) as $library) {
            $libraries[$library->id()] = $library;
        }

        return $libraries;
    }

    /**
     * The library of a value: the `resource` it keeps, among the libraries, or the only one.
     *
     * @param  array<string, MediaResource>  $libraries
     * @param  array<array-key, mixed>  $entry
     */
    private static function libraryOf(array $libraries, array $entry): ?MediaResource
    {
        $named = $entry['resource'] ?? null;
        if (is_string($named)) {
            return $libraries[$named] ?? null;
        }

        return count($libraries) === 1 ? reset($libraries) : null;
    }

    /**
     * The library of a media item, such as the model of a relation: the first of the libraries
     * whose items are of its class, or the only one.
     *
     * @param  array<string, MediaResource>  $libraries
     */
    private static function libraryOfItem(array $libraries, object $item): ?MediaResource
    {
        foreach ($libraries as $library) {
            $class = $library->itemClass();
            if ($class !== null && $item instanceof $class) {
                return $library;
            }
        }

        return count($libraries) === 1 ? reset($libraries) : null;
    }

    /**
     * @param  array<string, MediaResource>  $libraries
     */
    private function convert(Media $field, array $libraries, mixed $value): mixed
    {
        if (! $field->isMultiple()) {
            return $this->convertEntry($libraries, $value);
        }

        return is_array($value) ? array_values(array_map(fn (mixed $entry): mixed => $this->convertEntry($libraries, $entry), $value)) : [];
    }

    /**
     * @param  array<string, MediaResource>  $libraries
     */
    private function convertEntry(array $libraries, mixed $entry): mixed
    {
        if (is_object($entry)) {
            $library = self::libraryOfItem($libraries, $entry);
            if ($library !== null) {
                return $this->reference($library, $entry);
            }
            // Not a media of the field's libraries: its id only, never the object
            $id = Media::idOf(data_get($entry, 'id'));

            return $id !== null ? ['id' => $id] : null;
        }
        $id = is_array($entry) ? Media::idOf($entry) : null;
        $library = is_array($entry) ? self::libraryOf($libraries, $entry) : null;
        $item = $id !== null && $library !== null ? $this->find($library, $id) : null;
        if (! is_array($entry) || $library === null || $item === null) {
            return $entry;
        }
        $reference = $this->reference($library, $item);
        if (isset($entry['transform'])) {
            $reference['transform'] = $entry['transform'];
        }

        return $reference;
    }

    /**
     * @param  list<string>  $ids
     */
    private function load(Resource $resource, array $ids): void
    {
        $key = $resource->id();
        $missing = array_values(array_unique(array_filter(
            $ids,
            fn (string $id): bool => ! array_key_exists($id, $this->found[$key] ?? []),
        )));
        if (count($missing) === 0) {
            return;
        }
        foreach ($missing as $id) {
            $this->found[$key][$id] = null;
        }
        $repository = $this->resources->repository($resource);
        $query = (new ResourceQuery(perPage: count($missing), scope: $resource->repositoryScope($this->request)))->withIds($missing);
        foreach ($repository->query($query)->items() as $item) {
            $id = $repository->id($item);
            if (array_key_exists($id, $this->found[$key])) {
                $this->found[$key][$id] = $item;
            }
        }
    }

    /**
     * The entries of a serialized value: one, a list, or those of every locale.
     *
     * @return list<mixed>
     */
    private static function entries(Media $field, mixed $value): array
    {
        $values = $field->isLocalized() && is_array($value) && ! array_key_exists('id', $value) ? array_values($value) : [$value];
        $entries = [];
        foreach ($values as $localized) {
            if ($field->isMultiple()) {
                array_push($entries, ...(is_array($localized) ? array_values($localized) : []));
            } else {
                $entries[] = $localized;
            }
        }

        return $entries;
    }
}
