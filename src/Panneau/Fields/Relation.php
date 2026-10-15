<?php

namespace Panneau\Fields;

use Closure;
use Illuminate\Support\Collection;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * Items of another resource. A value is an item id, or an object with an `id`; with
 * `multiple()`, a list of them. The repository receives the ids.
 *
 * Items are serialized as references to the related items: `{ "id": "12", "label": "Ada" }`.
 */
class Relation extends Field
{
    protected ?string $resource = null;

    protected bool $multiple = false;

    public function type(): string
    {
        return 'relation';
    }

    /**
     * The related resource: its class, or its id.
     */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * The related resource, as given to `resource()`.
     */
    public function relatedResource(): ?string
    {
        return $this->resource;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    protected function typeDefinition(): array
    {
        return array_filter([
            'resource' => $this->resource,
            'multiple' => $this->multiple ?: null,
        ], fn (mixed $value): bool => $value !== null);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return $this->multiple ? ['array'] : [$this->idRule($context)];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if ($this->multiple) {
            $rules->add($path.'.*', ['required', $this->idRule($context)]);
        }
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($this->multiple) {
            return is_array($value) ? array_values(array_map(self::idOf(...), $value)) : [];
        }

        return self::idOf($value);
    }

    /**
     * Turns related items read from an item into a list, or one item; the serializer turns
     * them into references.
     */
    protected function serializeValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->values()->all();
        }

        return $value;
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function idRule(ValidationContext $context): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context): void {
            $id = self::idOf($value);
            if ($id === null) {
                $fail(__('The :attribute must be an item id.'));

                return;
            }
            if ($this->resource !== null && $context->relationExists !== null
                && ! ($context->relationExists)($this->resource, $id)) {
                $fail(__('The selected :attribute is invalid.'));
            }
        };
    }

    private static function idOf(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }
        if (is_int($value) || is_string($value) && $value !== '') {
            return (string) $value;
        }

        return null;
    }
}
