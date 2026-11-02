<?php

namespace Panneau\Fields;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Panneau\Forms\FormMode;
use Panneau\Forms\RuleSet;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;

/**
 * A password, for the users resources. In the definition, a `text` field with
 * `settings.secret`: a password input, with `autocomplete="new-password"`.
 *
 * - **Write-only.** Its value is never sent: items give `null`, and the form starts empty.
 * - **Required to create the item,** optional to update it: an empty value keeps the current
 *   password, and the repository receives no value for it. `optional()` makes it optional to
 *   create too.
 * - **Laravel's password rules,** `Password::defaults()`: 8 characters, unless the app sets
 *   others. `rules()` adds more.
 * - **Hashed once,** with `Hash::make()`: the repository receives the hash, never the
 *   password. `hashed(false)` leaves it to the repository, for one that hashes what it
 *   receives.
 */
class Password extends Text
{
    private bool $hashed = true;

    private bool $requiredToCreate = true;

    /**
     * Whether the field hashes the password, or the repository does.
     */
    public function hashed(bool $hashed = true): static
    {
        $this->hashed = $hashed;

        return $this;
    }

    /**
     * Optional to create the item too.
     */
    public function optional(): static
    {
        $this->requiredToCreate = false;

        return $this;
    }

    public function resolve(object $item): mixed
    {
        return null;
    }

    public function omits(mixed $value, ValidationContext $context): bool
    {
        return $value === null || $value === '';
    }

    public function collectRules(RuleSet $rules, string $path, mixed $value, array $siblings, string $prefix, ValidationContext $context): void
    {
        parent::collectRules($rules, $path, $value, $siblings, $prefix, $context);
        if ($this->requiredToCreate && $context->mode === FormMode::Create) {
            $rules->add($path, ['required']);
        }
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['string', PasswordRule::defaults()];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        $definition = parent::toDefinition();
        $definition['settings'] = ['secret' => true, ...($definition['settings'] ?? [])];
        // The length of Password::defaults(), so the app checks it before saving
        $min = PasswordRule::defaults()->appliedRules()['min'] ?? null;
        if (is_int($min) && ! $this->hasRule('min')) {
            $definition['rules'] = [...($definition['rules'] ?? []), ['rule' => 'min', 'value' => $min]];
        }

        return $definition;
    }

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        return $this->hashed && is_string($value) && ! Hash::isHashed($value) ? Hash::make($value) : $value;
    }
}
