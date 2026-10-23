<?php

namespace Panneau\Fields;

use Closure;
use Panneau\Forms\FieldCheck;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * A slug: it follows another field of the form (`from()`), in its own locale when it is
 * localized.
 *
 * With `unique()`, `url()` or `disabledWhen()`, the field checks its value with the backend as
 * it is typed (`check` in the definition): whether another item has it, a free value close to it,
 * and the URL it gives the item. Only a slug at the top of a resource checks; uniqueness is
 * validated again when the item is saved.
 */
class Slug extends Text
{
    /**
     * How many suggestions (`-2`, `-3`…) are tried before giving up on one.
     */
    public const SUGGESTIONS = 50;

    protected ?string $from = null;

    protected bool $unique = false;

    /**
     * Narrows the uniqueness query: receives the query and the form's values.
     */
    protected ?Closure $uniqueScope = null;

    /**
     * The URL of a slug: receives the slug, its locale, the form's values and the item's id.
     */
    protected ?Closure $url = null;

    /**
     * Whether the field applies to an item: receives the form's values and the item's id.
     */
    protected ?Closure $disabledWhen = null;

    public function type(): string
    {
        return 'slug';
    }

    /**
     * The field the slug is generated from, in the form.
     */
    public function from(string $field): static
    {
        $this->from = $field;

        return $this;
    }

    /**
     * No other item of the resource, within its scope, has the same slug: per locale for a
     * localized slug, and excluding the item being updated. Saving refuses a taken slug with a
     * `422` on its path (`slug`, `slug.fr`), and the field checks it as it is typed.
     *
     * The closure narrows the query of an `EloquentRepository` further, such as per parent:
     * `fn (Builder $query, array $values) => $query->where('parent_id', $values['parent'] ?? null)`.
     * It receives the query and the form's values, keyed by field name, as they would be saved
     * (relations as ids): the values sent, over those of the item being updated. They are not
     * validated yet. Repositories that implement `ChecksSlugs` answer the lookup themselves,
     * without the closure.
     *
     * Only for a slug at the top of a resource.
     */
    public function unique(?Closure $scope = null): static
    {
        $this->unique = true;
        $this->uniqueScope = $scope;

        return $this;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * The closure given to `unique()`, if any.
     */
    public function uniqueScope(): ?Closure
    {
        return $this->uniqueScope;
    }

    /**
     * The URL a slug gives the item, which the field shows and links to:
     * `fn (string $slug, ?string $locale, array $values, ?string $id): ?string`, with the locale
     * of a localized slug, the form's values (as for `unique()`) and the id of the item being
     * edited, or null when creating one.
     *
     * The prefix the field shows in front of the slug is that URL without the slug at its end
     * (`https://example.com/fr/vacances/` for `…/vacances/ete`). When the URL doesn't end with
     * the slug as typed (it encodes it, or adds a suffix), the field shows no prefix.
     */
    public function url(Closure $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * The field doesn't apply to some items, such as the home page's slug:
     * `fn (array $values, ?string $id): bool`. The check then answers `disabled`, and the field
     * is read-only. Saving doesn't change: hide the field from the form, or ignore the value in
     * a hook, if it must never be saved.
     */
    public function disabledWhen(Closure $disabled): static
    {
        $this->disabledWhen = $disabled;

        return $this;
    }

    public function hasCheck(): bool
    {
        return $this->unique || $this->url !== null || $this->disabledWhen !== null;
    }

    public function check(FieldCheck $check): array
    {
        if ($this->disabledWhen !== null && (bool) ($this->disabledWhen)($check->values, $check->id)) {
            return ['available' => true, 'suggestion' => null, 'prefix' => null, 'url' => null, 'disabled' => true];
        }

        $value = $check->value;
        $available = ! $this->unique || $value === '' || ! $check->exists($value, $this->uniqueScope);
        $url = $this->url !== null ? ($this->url)($value, $check->locale, $check->values, $check->id) : null;
        $url = is_string($url) ? $url : null;

        return [
            'available' => $available,
            'suggestion' => $available ? null : $this->suggest($value, $check),
            'prefix' => $url !== null && str_ends_with($url, $value) ? substr($url, 0, strlen($url) - strlen($value)) : null,
            'url' => $value !== '' ? $url : null,
            'disabled' => false,
        ];
    }

    /**
     * A free slug close to a taken one: the value with `-2`, `-3`… Null when none of the first
     * `SUGGESTIONS` is free.
     */
    protected function suggest(string $value, FieldCheck $check): ?string
    {
        $base = rtrim($value, '-');
        $base = $base !== '' ? $base : $value;
        for ($number = 2; $number < 2 + static::SUGGESTIONS; $number++) {
            $candidate = $base.'-'.$number;
            if (! $check->exists($candidate, $this->uniqueScope)) {
                return $candidate;
            }
        }

        return null;
    }

    public function collectRules(RuleSet $rules, string $path, mixed $value, array $siblings, string $prefix, ValidationContext $context): void
    {
        parent::collectRules($rules, $path, $value, $siblings, $prefix, $context);

        if (! $this->unique || $context->valueExists === null || $prefix !== '') {
            return;
        }
        if (! $this->localized) {
            $rules->add($path, [$this->uniqueRule($context->valueExists, null)]);

            return;
        }
        foreach ($context->locales as $locale) {
            $rules->add($path.'.'.$locale, [$this->uniqueRule($context->valueExists, $locale)]);
        }
    }

    /**
     * @param  Closure(string, string, ?string, ?Closure): bool  $exists
     * @return Closure(string, mixed, Closure): void
     */
    private function uniqueRule(Closure $exists, ?string $locale): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($exists, $locale): void {
            if (is_string($value) && $value !== '' && $exists($this->name, $value, $locale, $this->uniqueScope)) {
                $fail(__('The :attribute has already been taken.'));
            }
        };
    }

    protected function typeDefinition(): array
    {
        return $this->from !== null ? ['from' => $this->from] : [];
    }
}
