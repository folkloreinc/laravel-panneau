<?php

namespace Panneau\Fields;

use Closure;
use DateTimeInterface;
use Illuminate\Validation\Rule as LaravelRule;
use Panneau\Forms\Condition;
use Panneau\Forms\FormMode;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;
use Panneau\Forms\Values;

/**
 * A field of a resource or of an action. Each subclass is a protocol field type.
 *
 * Portable rules (`required()`, `min()`, `max()`, `pattern()`…) go into the definition, so the
 * frontend checks them too, and become Laravel rules. Server-only rules are added with
 * `rules()`.
 *
 * @phpstan-consistent-constructor
 */
abstract class Field
{
    protected ?string $label = null;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected bool $hasDefault = false;

    protected mixed $default = null;

    protected bool $localized = false;

    protected bool $readOnly = false;

    protected bool $createOnly = false;

    protected bool $updateOnly = false;

    protected ?Condition $visibleWhen = null;

    protected ?string $display = null;

    /**
     * Portable rules, in their protocol form.
     *
     * @var list<array<string, mixed>>
     */
    protected array $portableRules = [];

    /**
     * Server-only Laravel rules, or a closure that returns them.
     *
     * @var list<mixed>|(Closure(ValidationContext): list<mixed>)
     */
    protected array|Closure $serverRules = [];

    /**
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * @var (Closure(object, Field): mixed)|null
     */
    protected ?Closure $resolveUsing = null;

    public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * The protocol type: a built-in type, or a type provided by a plugin.
     */
    abstract public function type(): string;

    public function name(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    /**
     * The value is an object keyed by locale (`{ "fr": "…", "en": "…" }`).
     */
    public function localized(bool $localized = true): static
    {
        $this->localized = $localized;

        return $this;
    }

    public function isLocalized(): bool
    {
        return $this->localized;
    }

    /**
     * Shown, never saved.
     */
    public function readOnly(bool $readOnly = true): static
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    /**
     * Only saved when the item is created.
     */
    public function createOnly(bool $createOnly = true): static
    {
        $this->createOnly = $createOnly;

        return $this;
    }

    /**
     * Only saved when the item is updated.
     */
    public function updateOnly(bool $updateOnly = true): static
    {
        $this->updateOnly = $updateOnly;

        return $this;
    }

    /**
     * Shows the field only when a condition on the other values is true. Hidden fields are
     * neither validated nor saved.
     *
     * @param  Condition|array<string, mixed>  $condition
     */
    public function visibleWhen(Condition|array $condition): static
    {
        $this->visibleWhen = $condition instanceof Condition ? $condition : Condition::fromArray($condition);

        return $this;
    }

    /**
     * How the value is displayed in lists, when it differs from the type's default.
     */
    public function display(string $display): static
    {
        $this->display = $display;

        return $this;
    }

    /**
     * Settings passed as is to the frontend.
     *
     * @param  array<string, mixed>  $settings
     */
    public function settings(array $settings): static
    {
        $this->settings = array_merge($this->settings, $settings);

        return $this;
    }

    /**
     * Reads the value from the item with a closure, instead of the property of the same name.
     *
     * @param  Closure(object, Field): mixed  $resolve
     */
    public function resolveUsing(Closure $resolve): static
    {
        $this->resolveUsing = $resolve;

        return $this;
    }

    public function required(?string $message = null): static
    {
        return $this->rule('required', message: $message);
    }

    public function isRequired(): bool
    {
        return $this->hasRule('required');
    }

    /**
     * Minimum: the value of a number, the length of a text or an array.
     */
    public function min(int|float $value, ?string $message = null): static
    {
        return $this->rule('min', $value, $message);
    }

    /**
     * Maximum: the value of a number, the length of a text or an array.
     */
    public function max(int|float $value, ?string $message = null): static
    {
        return $this->rule('max', $value, $message);
    }

    /**
     * A regular expression, in the JavaScript syntax without delimiters (`^[a-z]+$`).
     */
    public function pattern(string $pattern, ?string $flags = null, ?string $message = null): static
    {
        return $this->rule('pattern', $pattern, $message, $flags !== null ? ['flags' => $flags] : []);
    }

    /**
     * @param  array<array-key, string|int|float|bool>  $values
     */
    public function in(array $values, ?string $message = null): static
    {
        return $this->rule('in', array_values($values), $message);
    }

    /**
     * Before a date (ISO 8601), or before the value of another field: `before(field: 'end')`.
     */
    public function before(?string $date = null, ?string $field = null, ?string $message = null): static
    {
        return $this->rule('before', $field !== null ? ['field' => $field] : $date, $message);
    }

    /**
     * After a date (ISO 8601), or after the value of another field: `after(field: 'start')`.
     */
    public function after(?string $date = null, ?string $field = null, ?string $message = null): static
    {
        return $this->rule('after', $field !== null ? ['field' => $field] : $date, $message);
    }

    /**
     * Server-only Laravel rules, such as uniqueness. A closure receives the validation context,
     * with the item being updated.
     *
     * @param  list<mixed>|(Closure(ValidationContext): list<mixed>)  $rules
     */
    public function rules(array|Closure $rules): static
    {
        $this->serverRules = $rules;

        return $this;
    }

    /**
     * Adds a portable rule.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function rule(string $rule, mixed $value = null, ?string $message = null, array $extra = []): static
    {
        $this->portableRules = array_values(array_filter(
            $this->portableRules,
            fn (array $existing): bool => $existing['rule'] !== $rule,
        ));
        $definition = ['rule' => $rule];
        if ($value !== null) {
            $definition['value'] = $value;
        }
        $definition = array_merge($definition, $extra);
        if ($message !== null) {
            $definition['message'] = $message;
        }
        $this->portableRules[] = $definition;

        return $this;
    }

    protected function hasRule(string $rule): bool
    {
        foreach ($this->portableRules as $existing) {
            if ($existing['rule'] === $rule) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the field is saved in this mode.
     */
    public function isEditable(FormMode $mode): bool
    {
        return ! $this->readOnly
            && ! ($mode === FormMode::Create && $this->updateOnly)
            && ! ($mode === FormMode::Update && $this->createOnly);
    }

    /**
     * Whether the field is visible, given the values at its level.
     *
     * @param  array<array-key, mixed>  $values
     */
    public function isVisible(array $values): bool
    {
        return $this->visibleWhen === null || $this->visibleWhen->evaluate($values);
    }

    /**
     * Reads the field's value from an item.
     */
    public function resolve(object $item): mixed
    {
        $value = $this->resolveUsing !== null
            ? ($this->resolveUsing)($item, $this)
            : data_get($item, $this->name);

        return $this->serialize($value);
    }

    /**
     * Turns a value read from an item into its JSON form.
     */
    public function serialize(mixed $value): mixed
    {
        if ($this->localized && is_array($value)) {
            return array_map(fn (mixed $localized): mixed => $this->serializeValue($localized), $value);
        }

        return $this->serializeValue($value);
    }

    /**
     * Turns one value (one locale of a localized field) into its JSON form.
     */
    protected function serializeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    /**
     * The field in the protocol's definition.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        $definition = ['name' => $this->name, 'type' => $this->type()];
        foreach (['label', 'help', 'placeholder', 'display'] as $property) {
            if ($this->{$property} !== null) {
                $definition[$property] = $this->{$property};
            }
        }
        if ($this->hasDefault) {
            $definition['default'] = $this->default;
        }
        foreach (['localized', 'readOnly', 'createOnly', 'updateOnly'] as $property) {
            if ($this->{$property} === true) {
                $definition[$property] = true;
            }
        }
        if (count($this->portableRules) > 0) {
            $definition['rules'] = $this->portableRules;
        }
        if ($this->visibleWhen !== null) {
            $definition['visibleWhen'] = $this->visibleWhen->toArray();
        }
        $definition = array_merge($definition, $this->typeDefinition());
        if (count($this->settings) > 0) {
            $definition['settings'] = $this->settings;
        }

        return $definition;
    }

    /**
     * Properties of the definition specific to the type: `options`, `multiple`, `resource`…
     *
     * @return array<string, mixed>
     */
    protected function typeDefinition(): array
    {
        return [];
    }

    /**
     * Adds the Laravel rules of the field's value at a path.
     *
     * @param  array<array-key, mixed>  $siblings  The values at the field's level.
     */
    public function collectRules(RuleSet $rules, string $path, mixed $value, array $siblings, string $prefix, ValidationContext $context): void
    {
        $rules->attribute($path, $this->label ?? str_replace('_', ' ', $this->name));
        $server = $this->serverRules instanceof Closure ? ($this->serverRules)($context) : $this->serverRules;

        if (! $this->localized) {
            $rules->add($path, [
                $this->isRequired() ? 'required' : 'nullable',
                ...$this->valueRules($context),
                ...$this->portableLaravelRules($rules, $path, $prefix),
                ...$server,
            ]);
            $this->collectNestedRules($rules, $path, $value, $context);

            return;
        }

        $rules->add($path, [$this->isRequired() ? 'required' : 'nullable', 'array', ...$server]);
        foreach ($context->locales as $locale) {
            $localePath = $path.'.'.$locale;
            $rules->attribute($localePath, ($this->label ?? str_replace('_', ' ', $this->name)).' ('.$locale.')');
            $rules->add($localePath, [
                $this->isRequired() ? 'required' : 'nullable',
                ...$this->valueRules($context),
                ...$this->portableLaravelRules($rules, $localePath, $prefix),
            ]);
        }
    }

    /**
     * The Laravel rules of the type, for one value: `string`, `numeric`, `array`…
     *
     * @return list<mixed>
     */
    protected function valueRules(ValidationContext $context): array
    {
        return [];
    }

    /**
     * Adds the rules of nested values, for groups and repeaters.
     */
    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void {}

    /**
     * The portable rules, as Laravel rules.
     *
     * @return list<mixed>
     */
    protected function portableLaravelRules(RuleSet $rules, string $path, string $prefix): array
    {
        $laravel = [];
        foreach ($this->portableRules as $rule) {
            $converted = $this->toLaravelRule($rule, $prefix);
            if ($converted === null) {
                continue;
            }
            [$name, $laravelRule] = $converted;
            if ($name !== 'required') {
                $laravel[] = $laravelRule;
            }
            if (isset($rule['message']) && is_string($rule['message'])) {
                $rules->message($path, $name, $rule['message']);
            }
        }

        return $laravel;
    }

    /**
     * Converts one portable rule to a Laravel rule, with the name of the Laravel rule (for its
     * message). Returns null for rules the server doesn't check this way.
     *
     * @param  array<string, mixed>  $rule
     * @return array{string, mixed}|null
     */
    protected function toLaravelRule(array $rule, string $prefix): ?array
    {
        $value = $rule['value'] ?? null;

        return match ($rule['rule']) {
            'required' => ['required', 'required'],
            'min', 'max' => is_int($value) || is_float($value) ? [$rule['rule'], $rule['rule'].':'.$value] : null,
            'pattern' => is_string($value) ? ['regex', 'regex:'.self::regex($value, is_string($rule['flags'] ?? null) ? $rule['flags'] : '')] : null,
            'email' => ['email', 'email'],
            'url' => ['url', 'url'],
            'in' => is_array($value) ? ['in', LaravelRule::in($value)] : null,
            'before', 'after' => match (true) {
                is_string($value) => [$rule['rule'], $rule['rule'].':'.$value],
                is_array($value) && is_string($value['field'] ?? null) => [$rule['rule'], $rule['rule'].':'.$prefix.$value['field']],
                default => null,
            },
            default => null,
        };
    }

    /**
     * Converts a JavaScript regular expression to a PCRE pattern with delimiters.
     */
    private static function regex(string $pattern, string $flags): string
    {
        $escaped = preg_replace('#(?<!\\\\)/#', '\\/', $pattern) ?? $pattern;
        $modifiers = implode('', array_intersect(str_split($flags), ['i', 'm', 's']));

        return '/'.$escaped.'/u'.$modifiers;
    }

    /**
     * Keeps the declared part of a validated value: the configured locales of a localized
     * field, the declared fields of a group or a block.
     */
    public function extract(mixed $value, ValidationContext $context): mixed
    {
        if ($this->localized) {
            if (! is_array($value)) {
                return $value;
            }
            $localized = [];
            foreach ($context->locales as $locale) {
                if (array_key_exists($locale, $value)) {
                    $localized[$locale] = $this->extractValue($value[$locale], $context);
                }
            }

            return $localized;
        }

        return $this->extractValue($value, $context);
    }

    /**
     * Keeps the declared part of one value.
     */
    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        return $value;
    }

    /**
     * Whether a value is filled, as the protocol counts it.
     */
    protected static function filled(mixed $value): bool
    {
        return Values::filled($value);
    }
}
