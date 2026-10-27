<?php

namespace Panneau\Fields;

use ArrayObject;
use Closure;
use Illuminate\Support\Collection;
use LogicException;
use Panneau\Fields\Concerns\AcceptsFiles;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;
use Panneau\Media\MediaTransform;

/**
 * Media of libraries: resources of kind `media` (`MediaResource`). One media, or a list with
 * `multiple()`.
 *
 * - **The libraries** are named by `resource()`, by class or id: one, or several in the order
 *   the picker shows them. Without, the field allows every library of the definition.
 * - **Requests** send an id, or `{ "id", "resource", "transform" }` with the media's library and
 *   the edits of the image for this use (the protocol's `MediaTransform`). Ids are unique only
 *   within their library, so a field that allows several libraries needs the `resource` of each
 *   media, among its libraries. An id is found through its library's repository, within its
 *   scope (`scope()` and its media types): an unknown id, or one outside the scope, is a `422`.
 *   `accept()` and `maxSize()` are checked on the MIME type and size of the media found, never
 *   on what the client sends.
 * - **The repository receives** `{ "id": "…" }`, with the `resource` of the media when the field
 *   allows several libraries, and a `transform` when the value has one: a list of them with
 *   `multiple()`. With Eloquent, the attribute needs a JSON column with the `array` cast. With
 *   `transforms(false)`, it receives the ids only (a string, or a list of them), for a column
 *   of ids, and transforms are ignored: the field then allows one library only.
 * - **Items** give the protocol's `MediaReference`: `{ "id", "resource", "label", "media",
 *   "transform" }`, where `resource` is the media's library, `label` its `itemTitle` and `media`
 *   its `MediaInfo`. In groups and blocks, values give `{ "id", "resource", "transform" }`, as
 *   the repository received them.
 */
class Media extends Field
{
    use AcceptsFiles {
        typeDefinition as filesTypeDefinition;
    }

    /**
     * @var list<string>
     */
    protected array $resources = [];

    protected bool $transforms = true;

    public function type(): string
    {
        return 'media';
    }

    /**
     * The libraries the field allows: classes or ids of `MediaResource`s, one, a list
     * (`resource([Images::class, Documents::class])`) or several arguments, in the order the
     * picker shows them. Without, the field allows every library of the definition.
     *
     * @param  string|array<array-key, string>  $resource
     */
    public function resource(string|array $resource, string ...$more): static
    {
        $resources = [...(is_string($resource) ? [$resource] : array_values($resource)), ...array_values($more)];
        $this->resources = array_values(array_unique($resources));
        $this->checkLibraries(count($this->resources));

        return $this;
    }

    /**
     * The libraries the field names, as given to `resource()`. Empty: every library of the
     * definition.
     *
     * @return list<string>
     */
    public function libraries(): array
    {
        return $this->resources;
    }

    /**
     * The library, when the field names one only.
     */
    public function relatedResource(): ?string
    {
        return count($this->resources) === 1 ? $this->resources[0] : null;
    }

    /**
     * Whether values keep the edits of the image for this use (`transform`). Without, the
     * repository receives ids only, so the field allows one library only.
     */
    public function transforms(bool $transforms = true): static
    {
        $this->transforms = $transforms;
        $this->checkLibraries(count($this->resources));

        return $this;
    }

    public function keepsTransforms(): bool
    {
        return $this->transforms;
    }

    protected function typeDefinition(): array
    {
        $definition = $this->filesTypeDefinition();
        if (count($this->resources) > 0) {
            $definition['resource'] = count($this->resources) === 1 ? $this->resources[0] : $this->resources;
        }

        return $definition;
    }

    /**
     * A localized field checks each locale like a field that isn't.
     */
    public function collectRules(RuleSet $rules, string $path, mixed $value, array $siblings, string $prefix, ValidationContext $context): void
    {
        if (! $this->localized) {
            parent::collectRules($rules, $path, $value, $siblings, $prefix, $context);

            return;
        }
        $attribute = $this->label ?? str_replace('_', ' ', $this->name);
        $rules->attribute($path, $attribute);
        $server = $this->serverRules instanceof Closure ? ($this->serverRules)($context) : $this->serverRules;
        $rules->add($path, [$this->isRequired() ? 'required' : 'nullable', 'array', ...$server]);
        foreach ($context->locales as $locale) {
            $localePath = $path.'.'.$locale;
            $rules->attribute($localePath, $attribute.' ('.$locale.')');
            $rules->add($localePath, [
                $this->isRequired() ? 'required' : 'nullable',
                ...$this->valueRules($context),
                ...$this->portableLaravelRules($rules, $localePath, $prefix),
            ]);
            $this->collectNestedRules($rules, $localePath, is_array($value) ? ($value[$locale] ?? null) : null, $context);
        }
    }

    protected function valueRules(ValidationContext $context): array
    {
        return $this->multiple ? ['array', 'list'] : [];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        $libraries = $this->allowedLibraries($context);
        $entryRules = [$this->mediaRule($context, $libraries), ...$this->foundMediaRules($context, $libraries)];
        if ($this->multiple) {
            $rules->attribute($path.'.*', $this->label ?? str_replace('_', ' ', $this->name));
            $rules->add($path.'.*', ['required', ...$entryRules]);
        } else {
            $rules->add($path, $entryRules);
        }
    }

    /**
     * `accept` and `maxSize` are checked on each media found, by `collectNestedRules()`.
     */
    protected function toLaravelRule(array $rule, string $prefix): ?array
    {
        return in_array($rule['rule'], ['accept', 'maxSize'], true) ? null : parent::toLaravelRule($rule, $prefix);
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if ($value === null) {
            return null;
        }
        $keepsLibrary = count($this->allowedLibraries($context)) > 1;
        if ($this->multiple) {
            return is_array($value) ? array_values(array_map(fn (mixed $entry): array|string|null => $this->extractEntry($entry, $keepsLibrary), $value)) : [];
        }

        return $this->extractEntry($value, $keepsLibrary);
    }

    /**
     * Turns a value read from an item into references (`{ "id", "resource", "transform" }`), or a
     * list of them. The value is what the repository received, ids, or media items (objects, such as the
     * models of a relation), which the serializer turns into references.
     */
    protected function serializeValue(mixed $value): mixed
    {
        if (is_string($value) && in_array(substr(ltrim($value), 0, 1), ['[', '{'], true)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }
        if ($value instanceof Collection) {
            $value = $value->values()->all();
        } elseif ($value instanceof ArrayObject) {
            $value = $value->getArrayCopy();
        }
        if (! $this->multiple) {
            return $this->serializeEntry($value);
        }
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_values(array_filter(array_map($this->serializeEntry(...), $value), fn (mixed $entry): bool => $entry !== null));
    }

    /**
     * The id of a value: an id, or an object with an `id`.
     */
    public static function idOf(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }
        if (is_int($value) || is_string($value) && $value !== '') {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  bool  $keepsLibrary  Whether the field allows several libraries, so that the value
     *                              keeps the library of the media.
     * @return array{id: string, resource?: string, transform?: array<string, mixed>}|string|null
     */
    private function extractEntry(mixed $value, bool $keepsLibrary): array|string|null
    {
        $id = self::idOf($value);
        if ($id === null || ! $this->transforms) {
            return $id;
        }
        $entry = ['id' => $id];
        if ($keepsLibrary && is_array($value) && is_string($value['resource'] ?? null)) {
            $entry['resource'] = $value['resource'];
        }
        $transform = is_array($value) ? MediaTransform::normalize($value['transform'] ?? null) : null;
        if ($transform !== null) {
            $entry['transform'] = $transform;
        }

        return $entry;
    }

    private function serializeEntry(mixed $value): mixed
    {
        if ($value instanceof ArrayObject) {
            $value = $value->getArrayCopy();
        }
        if (is_object($value)) {
            return $value;
        }
        $id = self::idOf($value);
        if ($id === null) {
            return null;
        }
        $reference = ['id' => $id];
        if ($this->transforms && is_array($value) && is_string($value['resource'] ?? null) && $value['resource'] !== '') {
            $reference['resource'] = $value['resource'];
        }
        $transform = $this->transforms && is_array($value) ? MediaTransform::normalize($value['transform'] ?? null) : null;
        if ($transform !== null) {
            $reference['transform'] = $transform;
        }

        return $reference;
    }

    /**
     * Checks that a value is an id, or an object with an `id`, the `resource` of the media among
     * the field's libraries (required when it allows several), and an optional `transform`, of a
     * media of that library, within its scope.
     *
     * @param  list<string>  $libraries  From `allowedLibraries()`.
     * @return Closure(string, mixed, Closure): void
     */
    private function mediaRule(ValidationContext $context, array $libraries): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context, $libraries): void {
            $id = self::idOf($value);
            if ($id === null) {
                $fail(__('The :attribute must be a media.'));

                return;
            }
            $library = self::libraryOf($value, $libraries);
            if ($library === false) {
                $fail(is_array($value) && array_key_exists('resource', $value) && $value['resource'] !== null
                    ? __('The library of the :attribute is not one this field allows.')
                    : __('The :attribute allows several libraries: name the library.'));

                return;
            }
            if ($this->transforms && is_array($value) && ($error = MediaTransform::error($value['transform'] ?? null)) !== null) {
                $fail(__('The transform of the :attribute is invalid: :error.', ['error' => $error]));

                return;
            }
            if ($context->media !== null && ($context->media)($library, $id) === null) {
                $fail(__('The selected :attribute is invalid.'));
            }
        };
    }

    /**
     * The ids of the libraries the field allows for a request: those it names, or every library
     * of the definition (`ValidationContext::$libraries`). Without a registry to ask, those it
     * names.
     *
     * @return list<string>
     */
    private function allowedLibraries(ValidationContext $context): array
    {
        $libraries = $context->libraries !== null ? ($context->libraries)($this->resources) : $this->resources;
        $this->checkLibraries(count($libraries));

        return $libraries;
    }

    /**
     * The library of a value: the `resource` it names, among the libraries, or the only library.
     * Null for the only library of the registry, when the field doesn't know its libraries.
     * False when the value names a library the field doesn't allow, or none when it allows
     * several.
     *
     * @param  list<string>  $libraries
     */
    private static function libraryOf(mixed $value, array $libraries): string|false|null
    {
        $named = is_array($value) ? ($value['resource'] ?? null) : null;
        if ($named !== null) {
            return is_string($named) && (count($libraries) === 0 || in_array($named, $libraries, true)) ? $named : false;
        }

        return count($libraries) > 1 ? false : ($libraries[0] ?? null);
    }

    /**
     * A field that keeps ids only (`transforms(false)`) can't keep the library of each media, so
     * it allows one library only.
     */
    private function checkLibraries(int $count): void
    {
        if (! $this->transforms && $count > 1) {
            throw new LogicException(sprintf(
                'The media field "%s" keeps ids only (transforms(false)), so it allows one library only: name it with resource().',
                $this->name,
            ));
        }
    }

    /**
     * The `accept` and `maxSize` rules, checked on the MIME type and size of the media found. A
     * media that isn't found is reported by the media rule.
     *
     * @param  list<string>  $libraries  From `allowedLibraries()`.
     * @return list<Closure(string, mixed, Closure): void>
     */
    private function foundMediaRules(ValidationContext $context, array $libraries): array
    {
        $found = function (mixed $value) use ($context, $libraries): ?array {
            $id = self::idOf($value);
            $library = self::libraryOf($value, $libraries);

            return $id !== null && $library !== false && $context->media !== null ? ($context->media)($library, $id) : null;
        };
        $checks = [];
        foreach ($this->portableRules as $rule) {
            $value = $rule['value'] ?? null;
            $message = isset($rule['message']) && is_string($rule['message']) ? $rule['message'] : null;
            if ($rule['rule'] === 'accept' && is_array($value)) {
                $types = array_values(array_filter($value, is_string(...)));
                $checks[] = function (string $attribute, mixed $entry, Closure $fail) use ($found, $types, $message): void {
                    $media = $found($entry);
                    $mime = $media['mime'] ?? null;
                    if ($media !== null && (! is_string($mime) || ! self::matchesMime($mime, $types))) {
                        $fail($message ?? __('The :attribute must be a file of type: :types.', ['types' => implode(', ', $types)]));
                    }
                };
            }
            if ($rule['rule'] === 'maxSize' && is_int($value)) {
                $checks[] = function (string $attribute, mixed $entry, Closure $fail) use ($found, $value, $message): void {
                    $media = $found($entry);
                    $size = $media['size'] ?? null;
                    if ($media !== null && (! is_int($size) || $size > $value)) {
                        $fail($message ?? __('The :attribute must not be larger than :size bytes.', ['size' => $value]));
                    }
                };
            }
        }

        return $checks;
    }
}
