<?php

namespace Panneau\Fields;

use BackedEnum;
use Illuminate\Validation\Rule as LaravelRule;
use InvalidArgumentException;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * A choice among options. The server only accepts the values of the options.
 */
class Select extends Field
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
     * The choices: a backed enum class, a list of options (`['value' => …, 'label' => …]`), or
     * labels keyed by value. An enum case's label comes from its `label()` method, if it has
     * one, and its tone from its `tone()` method.
     *
     * An option can have a `description`, `disabled`, and a `tone`, which colors it where it
     * shows as a badge, in lists and details: `neutral` (the default), `accent`, `success`,
     * `warning`, `danger`, `info`, or a tone of the theme (`panneau.theme.tones`).
     *
     * @param  class-string<BackedEnum>|array<array-key, mixed>  $options
     */
    public function options(string|array $options): static
    {
        $this->options = self::normalizeOptions($options);

        return $this;
    }

    /**
     * The value is a list of choices.
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * @return list<string|int|float|bool>
     */
    public function values(): array
    {
        return array_map(fn (array $option): mixed => $option['value'], $this->options);
    }

    /**
     * @param  class-string<BackedEnum>|array<array-key, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public static function normalizeOptions(string|array $options): array
    {
        if (is_string($options)) {
            if (! is_subclass_of($options, BackedEnum::class)) {
                throw new InvalidArgumentException(sprintf('%s is not a backed enum.', $options));
            }

            return array_map(function (BackedEnum $case): array {
                $option = [
                    'value' => $case->value,
                    'label' => method_exists($case, 'label') ? (string) $case->label() : $case->name,
                ];
                $tone = method_exists($case, 'tone') ? $case->tone() : null;
                if ($tone !== null) {
                    $option['tone'] = (string) $tone;
                }

                return $option;
            }, $options::cases());
        }

        $normalized = [];
        foreach ($options as $key => $option) {
            $normalized[] = is_array($option)
                ? $option
                : ['value' => $key, 'label' => (string) $option];
        }

        return $normalized;
    }

    protected function typeDefinition(): array
    {
        return array_filter([
            'options' => $this->options,
            'multiple' => $this->multiple ?: null,
        ], fn (mixed $value): bool => $value !== null);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return $this->multiple ? ['array'] : [LaravelRule::in($this->values())];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if ($this->multiple) {
            $rules->add($path.'.*', [LaravelRule::in($this->values())]);
        }
    }
}
