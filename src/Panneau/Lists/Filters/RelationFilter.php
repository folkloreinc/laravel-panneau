<?php

namespace Panneau\Lists\Filters;

/**
 * A filter on items of another resource, by id.
 */
class RelationFilter extends Filter
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

    public function relatedResource(): ?string
    {
        return $this->resource;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! $this->multiple) {
                return null;
            }
            $ids = array_values(array_filter(array_map(fn (mixed $id): ?string => is_scalar($id) && $id !== '' ? (string) $id : null, $value)));

            return count($ids) > 0 ? $ids : null;
        }

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }

    protected function typeDefinition(): array
    {
        return array_filter([
            'resource' => $this->resource,
            'multiple' => $this->multiple ?: null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
