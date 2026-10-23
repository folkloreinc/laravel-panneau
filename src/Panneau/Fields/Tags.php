<?php

namespace Panneau\Fields;

use BackedEnum;
use Illuminate\Validation\Rule as LaravelRule;
use Panneau\Forms\RuleSet;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;

/**
 * A list of strings. `options()` suggest tags; with `allowCustom(false)`, only their values are
 * accepted. `min()` and `max()` count the tags. Saving trims the tags and drops empty and
 * repeated ones.
 */
class Tags extends Field
{
    /**
     * @var list<array<string, mixed>>
     */
    protected array $options = [];

    protected bool $allowCustom = true;

    public function type(): string
    {
        return 'tags';
    }

    /**
     * Suggested tags: a backed enum class, a list of options (`['value' => …, 'label' => …]`), or
     * labels keyed by value.
     *
     * @param  class-string<BackedEnum>|array<array-key, mixed>  $options
     */
    public function options(string|array $options): static
    {
        $this->options = Select::normalizeOptions($options);

        return $this;
    }

    /**
     * Whether tags outside the options are accepted. Defaults to true.
     */
    public function allowCustom(bool $allowCustom = true): static
    {
        $this->allowCustom = $allowCustom;

        return $this->settings(['allowCustom' => $allowCustom]);
    }

    protected function typeDefinition(): array
    {
        return count($this->options) > 0 ? ['options' => $this->options] : [];
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array'];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        $rules->add($path.'.*', [
            'string',
            'max:255',
            ...($this->allowCustom ? [] : [LaravelRule::in(array_map(
                fn (array $option): string => (string) $option['value'],
                $this->options,
            ))]),
        ]);
    }

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $tags = [];
        foreach ($value as $tag) {
            $tag = is_string($tag) ? trim($tag) : '';
            if ($tag !== '' && ! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }
}
