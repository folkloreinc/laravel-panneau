<?php

namespace Panneau\Fields;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Panneau\Forms\FormMode;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;

/**
 * A password, for the users resources. In the definition, a `text` field with
 * `settings.secret`: a password input, with `autocomplete="new-password"`.
 *
 * - **Write-only.** Its value is never sent: items give `null`, and the form starts empty.
 * - **Required to create the item,** optional to update it: an empty value keeps the current
 *   password, and the repository receives no value for it. The definition sends
 *   `{ "rule": "required", "mode": "create" }`, so the admin marks it required on create only.
 *   `optional()` makes it optional to create too; `required()` is the default, and applies to
 *   an update only with `mode: FormMode::Update`.
 * - **Laravel's password rules,** `Password::defaults()`: 8 characters, unless the app sets
 *   others. `rules()` adds more.
 * - **Hashed once,** with `Hash::make()`: the repository receives the hash, never the
 *   password. `hashed(false)` leaves it to the repository, for one that hashes what it
 *   receives.
 */
class Password extends Text
{
    private bool $hashed = true;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->required();
    }

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
        return $this->removeRule('required');
    }

    /**
     * Required to create the item, the default: an empty value keeps the current password on an
     * update.
     */
    public function required(?string $message = null, ?FormMode $mode = null): static
    {
        return parent::required($message, $mode ?? FormMode::Create);
    }

    public function resolve(object $item): mixed
    {
        return null;
    }

    public function omits(mixed $value, ValidationContext $context): bool
    {
        return $value === null || $value === '';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['string', $this->defaultRule()];
    }

    /**
     * `Password::defaults()`: a `Password` rule, or any rule the app's callback returns.
     */
    private function defaultRule(): Rule
    {
        return PasswordRule::defaults();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        $definition = parent::toDefinition();
        $definition['settings'] = ['secret' => true, ...($definition['settings'] ?? [])];
        // The length of Password::defaults(), so the app checks it before saving
        $defaults = $this->defaultRule();
        $min = $defaults instanceof PasswordRule ? $defaults->appliedRules()['min'] ?? null : null;
        // In the modes where the field has no min of its own
        $modes = array_values(array_filter(FormMode::cases(), fn (FormMode $mode): bool => ! $this->hasRule('min', $mode)));
        if (is_int($min) && $modes !== []) {
            $rule = ['rule' => 'min', 'value' => $min];
            if (count($modes) === 1) {
                $rule['mode'] = $modes[0]->value;
            }
            $definition['rules'] = [...($definition['rules'] ?? []), $rule];
        }

        return $definition;
    }

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        // A hash the app's hasher accepts is kept as is, as Eloquent's `hashed` cast does: it would
        // be hashed twice. A bcrypt hash of a lower cost is one.
        $isHash = is_string($value) && Hash::isHashed($value) && Hash::verifyConfiguration($value);

        return $this->hashed && is_string($value) && ! $isHash ? Hash::make($value) : $value;
    }

    protected function sortsByValue(): bool
    {
        return false;
    }
}
