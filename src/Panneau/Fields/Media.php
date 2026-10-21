<?php

namespace Panneau\Fields;

use ArrayObject;
use Closure;
use Illuminate\Support\Collection;
use Panneau\Fields\Concerns\AcceptsFiles;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;
use Panneau\Media\MediaTransform;

/**
 * Media of a library: a resource of kind `media` (`MediaResource`). One media, or a list with
 * `multiple()`.
 *
 * - **The library** is named by `resource()`, by class or id. It can be left out when there is
 *   only one library.
 * - **Requests** send an id, or `{ "id", "transform" }` with the edits of the image for this use
 *   (the protocol's `MediaTransform`). An id is found through the library's repository, within
 *   its scope (`scope()` and its media types): an unknown id, or one outside the scope, is a
 *   `422`. `accept()` and `maxSize()` are checked on the MIME type and size of the media found,
 *   never on what the client sends.
 * - **The repository receives** `{ "id": "…" }`, with a `transform` when the value has one: a
 *   list of them with `multiple()`. With Eloquent, the attribute needs a JSON column with the
 *   `array` cast. With `transforms(false)`, it receives the ids only (a string, or a list of
 *   them), for a column of ids, and transforms are ignored.
 * - **Items** give the protocol's `MediaReference`: `{ "id", "label", "media", "transform" }`,
 *   where `label` is the media's `itemTitle` and `media` its `MediaInfo`. In groups and blocks,
 *   values give `{ "id", "transform" }`.
 */
class Media extends Field
{
    use AcceptsFiles {
        typeDefinition as filesTypeDefinition;
    }

    protected ?string $resource = null;

    protected bool $transforms = true;

    public function type(): string
    {
        return 'media';
    }

    /**
     * The library: the class or the id of a `MediaResource`. Optional when there is only one
     * library.
     */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * The library, as given to `resource()`.
     */
    public function relatedResource(): ?string
    {
        return $this->resource;
    }

    /**
     * Whether values keep the edits of the image for this use (`transform`). Without, the
     * repository receives ids only.
     */
    public function transforms(bool $transforms = true): static
    {
        $this->transforms = $transforms;

        return $this;
    }

    public function keepsTransforms(): bool
    {
        return $this->transforms;
    }

    protected function typeDefinition(): array
    {
        $definition = $this->filesTypeDefinition();
        if ($this->resource !== null) {
            $definition['resource'] = $this->resource;
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
        $entryRules = [$this->mediaRule($context), ...$this->foundMediaRules($context)];
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
        if ($this->multiple) {
            return is_array($value) ? array_values(array_map($this->extractEntry(...), $value)) : [];
        }

        return $this->extractEntry($value);
    }

    /**
     * Turns a value read from an item into references (`{ "id", "transform" }`), or a list of
     * them. The value is what the repository received, ids, or media items (objects, such as the
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
     * @return array{id: string, transform?: array<string, mixed>}|string|null
     */
    private function extractEntry(mixed $value): array|string|null
    {
        $id = self::idOf($value);
        if ($id === null || ! $this->transforms) {
            return $id;
        }
        $entry = ['id' => $id];
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
        $transform = $this->transforms && is_array($value) ? MediaTransform::normalize($value['transform'] ?? null) : null;
        if ($transform !== null) {
            $reference['transform'] = $transform;
        }

        return $reference;
    }

    /**
     * Checks that a value is an id, or an object with an `id` and an optional `transform`, of a
     * media of the library, within its scope.
     *
     * @return Closure(string, mixed, Closure): void
     */
    private function mediaRule(ValidationContext $context): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context): void {
            $id = self::idOf($value);
            if ($id === null) {
                $fail(__('The :attribute must be a media.'));

                return;
            }
            if ($this->transforms && is_array($value) && ($error = MediaTransform::error($value['transform'] ?? null)) !== null) {
                $fail(__('The transform of the :attribute is invalid: :error.', ['error' => $error]));

                return;
            }
            if ($context->media !== null && ($context->media)($this->resource, $id) === null) {
                $fail(__('The selected :attribute is invalid.'));
            }
        };
    }

    /**
     * The `accept` and `maxSize` rules, checked on the MIME type and size of the media found. A
     * media that isn't found is reported by the media rule.
     *
     * @return list<Closure(string, mixed, Closure): void>
     */
    private function foundMediaRules(ValidationContext $context): array
    {
        $found = function (mixed $value) use ($context): ?array {
            $id = self::idOf($value);

            return $id !== null && $context->media !== null ? ($context->media)($this->resource, $id) : null;
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
