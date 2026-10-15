<?php

namespace Panneau\Fields;

use Panneau\Forms\Form;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * A nested object, with its own fields.
 */
class Group extends Field
{
    /**
     * @var list<Field>
     */
    protected array $fields = [];

    public function type(): string
    {
        return 'group';
    }

    /**
     * @param  list<Field>  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    protected function typeDefinition(): array
    {
        return ['fields' => array_map(fn (Field $field): array => $field->toDefinition(), $this->fields)];
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array'];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if (is_array($value)) {
            Form::rules($this->fields, $value, $context->nested(), $path.'.', $rules);
        }
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        return is_array($value) ? Form::extract($this->fields, $value, $context->nested()) : $value;
    }

    public function serialize(mixed $value): mixed
    {
        if (! is_array($value) && ! is_object($value)) {
            return $value;
        }
        $serialized = [];
        foreach ($this->fields as $field) {
            $serialized[$field->name()] = $field->serialize(data_get($value, $field->name()));
        }

        return $serialized;
    }
}
