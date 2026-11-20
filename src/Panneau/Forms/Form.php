<?php

namespace Panneau\Forms;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use Panneau\Fields\Field;
use Panneau\Layout\Node;

/**
 * Validates values against fields, and keeps only the declared values.
 *
 * At each level (the form, a group, a repeater block), a field applies when it is editable in
 * this mode (not read-only, create-only or update-only), visible (`visibleWhen`), and, for a
 * partial update, present in the values. A partial update reads the conditions with the item's
 * values of the fields it doesn't send.
 *
 * At the top of a form, a field inside a layout node with `visibleWhen` (a tab, a section) is
 * visible when the conditions of every node that contains it are true, as if the field had them
 * itself; a field placed in several nodes is visible when one of its places is (panneau-js
 * `resolveFields()`, folkloreinc/panneau-js#386). A field that no node places has no conditions
 * of the layout.
 */
final class Form
{
    /**
     * Validates the input and returns the declared values only: hidden, read-only and unknown
     * values are dropped, and those a field omits (an empty password).
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $input
     * @param  list<Node>  $layout  The layout of the form, whose nodes can hide fields.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(ValidationFactory $factory, array $fields, array $input, ValidationContext $context, array $layout = []): array
    {
        $rules = self::rules($fields, $input, $context, layout: $layout);
        $validated = $factory
            ->make($input, $rules->rules(), $rules->messages(), $rules->attributes())
            ->validate();

        return self::extract($fields, $validated, $context, $layout);
    }

    /**
     * The Laravel rules of the fields that apply to these values.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @param  list<Node>  $layout  The layout of the form, whose nodes can hide fields.
     */
    public static function rules(array $fields, array $values, ValidationContext $context, string $prefix = '', ?RuleSet $rules = null, array $layout = []): RuleSet
    {
        $rules ??= new RuleSet;
        foreach (self::applicable($fields, $values, $context, $layout) as $field) {
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
     * @param  list<Node>  $layout  The layout of the form, whose nodes can hide fields.
     * @return array<string, mixed>
     */
    public static function extract(array $fields, array $values, ValidationContext $context, array $layout = []): array
    {
        $extracted = [];
        foreach (self::applicable($fields, $values, $context, $layout) as $field) {
            if (array_key_exists($field->name(), $values) && ! $field->omits($values[$field->name()], $context)) {
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
                // Kept elsewhere: the field saved it with the context
                if (! $field->isStored()) {
                    unset($values[$field->name()]);
                }
            } elseif ($nested) {
                $field->prepareAbsent($path, $context);
            }
        }

        return $values;
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @param  list<Node>  $layout
     * @return list<Field>
     */
    private static function applicable(array $fields, array $values, ValidationContext $context, array $layout): array
    {
        $placed = self::layoutConditions($layout);
        $visibility = $context->partial && $context->item !== null
            ? self::visibilityValues($fields, $values, $context->item, $placed)
            : $values;

        return array_values(array_filter(
            $fields,
            fn (Field $field): bool => $field->isEditable($context->mode)
                && $field->isVisible($visibility)
                && ($placed[$field->name()] ?? null)?->evaluate($visibility) !== false
                && (! $context->partial || array_key_exists($field->name(), $values)),
        ));
    }

    /**
     * The conditions the layout puts on the fields it places, by name: for each place, the
     * conditions of the nodes that contain it, from the root, all true; for a field in several
     * places, one of them. A field with a place without conditions has none.
     *
     * @param  list<Node>  $layout
     * @return array<string, Condition>
     */
    private static function layoutConditions(array $layout): array
    {
        /** @var array<string, list<Condition>|null> $places Null: a place without conditions. */
        $places = [];
        $visit = function (Node $node, array $conditions) use (&$visit, &$places): void {
            $condition = $node->getVisibleWhen();
            if ($condition !== null) {
                $conditions[] = $condition;
            }
            $children = $node->children();
            if (count($children) > 0) {
                foreach ($children as $child) {
                    $visit($child, $conditions);
                }

                return;
            }
            foreach ($node->fieldNames() as $name) {
                if (array_key_exists($name, $places) && $places[$name] === null) {
                    continue;
                }
                if (count($conditions) === 0) {
                    $places[$name] = null;

                    continue;
                }
                $places[$name] ??= [];
                $places[$name][] = count($conditions) === 1 ? $conditions[0] : Condition::all($conditions);
            }
        };
        foreach ($layout as $node) {
            $visit($node, []);
        }

        $conditions = [];
        foreach ($places as $name => $alternatives) {
            if ($alternatives !== null) {
                $conditions[$name] = count($alternatives) === 1 ? $alternatives[0] : Condition::any($alternatives);
            }
        }

        return $conditions;
    }

    /**
     * What the conditions of a partial update read: the values sent, and the item's values of
     * the fields they name that aren't sent. A field shown when `status` is `published`, sent
     * alone, applies to a published item (panneau-js decision 0043).
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @param  array<string, Condition>  $placed  The conditions of the layout.
     * @return array<array-key, mixed>
     */
    private static function visibilityValues(array $fields, array $values, object $item, array $placed): array
    {
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->name()] = $field;
        }
        $conditions = [...array_map(fn (Field $field): ?Condition => $field->getVisibleWhen(), $fields), ...array_values($placed)];
        foreach ($conditions as $condition) {
            foreach ($condition?->fields() ?? [] as $name) {
                if (! array_key_exists($name, $values) && isset($byName[$name])) {
                    $values[$name] = $byName[$name]->resolve($item);
                }
            }
        }

        return $values;
    }
}
