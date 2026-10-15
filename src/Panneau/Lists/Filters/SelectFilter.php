<?php

namespace Panneau\Lists\Filters;

use BackedEnum;
use Panneau\Fields\Select;

/**
 * A filter among options. Values that are not options are ignored.
 */
class SelectFilter extends Filter
{
    /**
     * @var list<array<string, mixed>>
     */
    protected array $options = [];

    protected bool $multiple = false;

    public function type(): string
    {
        return 'select';
    }

    /**
     * Same formats as `Select::options()`.
     *
     * @param  class-string<BackedEnum>|array<array-key, mixed>  $options
     */
    public function options(string|array $options): static
    {
        $this->options = Select::normalizeOptions($options);

        return $this;
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
            $values = array_values(array_filter(array_map($this->option(...), $value), fn (mixed $option): bool => $option !== null));

            return count($values) > 0 ? $values : null;
        }

        $option = $this->option($value);

        return $option !== null && $this->multiple ? [$option] : $option;
    }

    private function option(mixed $value): mixed
    {
        foreach ($this->options as $option) {
            if (is_scalar($value) && (string) $option['value'] === (string) $value) {
                return $option['value'];
            }
        }

        return null;
    }

    protected function typeDefinition(): array
    {
        return array_filter([
            'options' => $this->options,
            'multiple' => $this->multiple ?: null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
