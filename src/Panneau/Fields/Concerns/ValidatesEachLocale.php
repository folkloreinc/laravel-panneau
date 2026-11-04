<?php

namespace Panneau\Fields\Concerns;

use Closure;
use Panneau\Fields\Field;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * A localized field whose values have parts (a media, an address, a link) checks the parts of
 * each locale as a field that isn't localized checks those of its value: `poster.fr`,
 * `address.en.postalCode`.
 *
 * @mixin Field
 */
trait ValidatesEachLocale
{
    public function collectRules(RuleSet $rules, string $path, mixed $value, array $siblings, string $prefix, ValidationContext $context): void
    {
        if (! $this->localized) {
            parent::collectRules($rules, $path, $value, $siblings, $prefix, $context);

            return;
        }
        $attribute = $this->label ?? str_replace('_', ' ', $this->name);
        $rules->attribute($path, $attribute);
        $server = $this->serverRules instanceof Closure ? ($this->serverRules)($context) : $this->serverRules;
        $rules->add($path, [$this->isRequired($context->mode) ? 'required' : 'nullable', 'array', ...$server]);
        foreach ($context->locales as $locale) {
            $localePath = $path.'.'.$locale;
            $rules->attribute($localePath, $attribute.' ('.$locale.')');
            $rules->add($localePath, [
                $this->isRequired($context->mode) ? 'required' : 'nullable',
                ...$this->valueRules($context),
                ...$this->portableLaravelRules($rules, $localePath, $prefix, $context->mode),
            ]);
            $this->collectNestedRules($rules, $localePath, is_array($value) ? ($value[$locale] ?? null) : null, $context);
        }
    }
}
