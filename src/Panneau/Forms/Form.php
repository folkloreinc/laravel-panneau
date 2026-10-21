<?php

namespace Panneau\Forms;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use Panneau\Fields\Field;

/**
 * Validates values against fields, and keeps only the declared values.
 *
 * At each level (the form, a group, a repeater block), a field applies when it is editable in
 * this mode (not read-only, create-only or update-only), visible (`visibleWhen`), and, for a
 * partial update, present in the values.
 */
final class Form
{
    /**
     * Validates the input and returns the declared values only: hidden, read-only and unknown
     * values are dropped.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(ValidationFactory $factory, array $fields, array $input, ValidationContext $context): array
    {
        $rules = self::rules($fields, $input, $context);
        $validated = $factory
            ->make($input, $rules->rules(), $rules->messages(), $rules->attributes())
            ->validate();

        return self::extract($fields, $validated, $context);
    }

    /**
     * The Laravel rules of the fields that apply to these values.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     */
    public static function rules(array $fields, array $values, ValidationContext $context, string $prefix = '', ?RuleSet $rules = null): RuleSet
    {
        $rules ??= new RuleSet;
        foreach (self::applicable($fields, $values, $context) as $field) {
            $field->collectRules(
                $rules,
                $prefix.$field->name(),
                $values[$field->name()] ?? null,
                $values,
                $prefix,
                $context,
            );
        }

        return $rules;
    }

    /**
     * The declared values of the fields that apply to these values.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    public static function extract(array $fields, array $values, ValidationContext $context): array
    {
        $extracted = [];
        foreach (self::applicable($fields, $values, $context) as $field) {
            if (array_key_exists($field->name(), $values)) {
                $extracted[$field->name()] = $field->extract($values[$field->name()], $context);
            }
        }

        return $extracted;
    }

    /**
     * Turns validated values into the values the repository receives, with each field's
     * `prepare()`: a file field stores the files it received, for example.
     *
     * At a nested level (a group, a block), which is saved whole, the fields without a value
     * are told with `prepareAbsent()`: their previous value is gone.
     *
     * @template TKey of array-key
     *
     * @param  list<Field>  $fields
     * @param  array<TKey, mixed>  $values  Validated values, from `validate()`.
     * @return array<TKey, mixed>
     */
    public static function prepare(array $fields, array $values, SaveContext $context, string $prefix = '', bool $nested = false): array
    {
        foreach ($fields as $field) {
            $path = $prefix.$field->name();
            if (array_key_exists($field->name(), $values)) {
                $values[$field->name()] = $field->prepare($values[$field->name()], $path, $context);
            } elseif ($nested) {
                $field->prepareAbsent($path, $context);
            }
        }

        return $values;
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @return list<Field>
     */
    private static function applicable(array $fields, array $values, ValidationContext $context): array
    {
        return array_values(array_filter(
            $fields,
            fn (Field $field): bool => $field->isEditable($context->mode)
                && $field->isVisible($values)
                && (! $context->partial || array_key_exists($field->name(), $values)),
        ));
    }
}
