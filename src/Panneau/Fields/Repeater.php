<?php

namespace Panneau\Fields;

use Illuminate\Validation\Rule as LaravelRule;
use Panneau\Forms\Form;
use Panneau\Forms\RuleSet;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;

/**
 * A list of blocks: with `fields()`, each block has the same fields; with `types()`, each
 * block has a `type` and the fields of that type.
 */
class Repeater extends Field
{
    /**
     * @var list<Field>
     */
    protected array $fields = [];

    /**
     * @var list<BlockType>|null
     */
    protected ?array $types = null;

    public function type(): string
    {
        return 'repeater';
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
     * @param  list<BlockType>  $types
     */
    public function types(array $types): static
    {
        $this->types = $types;

        return $this;
    }

    protected function typeDefinition(): array
    {
        if ($this->types !== null) {
            return ['types' => array_map(fn (BlockType $type): array => $type->toDefinition(), $this->types)];
        }

        return ['fields' => array_map(fn (Field $field): array => $field->toDefinition(), $this->fields)];
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array', 'list'];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        $rules->add($path.'.*', ['array']);
        if ($this->types !== null) {
            $rules->add($path.'.*.type', ['required', LaravelRule::in(array_map(fn (BlockType $type): string => $type->id(), $this->types))]);
        }
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $index => $block) {
            if (is_array($block)) {
                Form::rules($this->blockFields($block), $block, $context->nested(), $path.'.'.$index.'.', $rules);
            }
        }
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_values(array_map(function (mixed $block) use ($context): mixed {
            if (! is_array($block)) {
                return $block;
            }
            $values = Form::extract($this->blockFields($block), $block, $context->nested());

            return $this->types !== null ? ['type' => $block['type'] ?? null, ...$values] : $values;
        }, $value));
    }

    /**
     * Prepares each block. The blocks replace the previous ones, so every field of the blocks
     * is also told that its values at `{path}.*` may be gone.
     */
    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        if (is_array($value)) {
            foreach ($value as $index => $block) {
                if (is_array($block)) {
                    $value[$index] = Form::prepare($this->blockFields($block), $block, $context, $path.'.'.$index.'.', nested: true);
                }
            }
        }
        $this->prepareAbsent($path, $context);

        return $value;
    }

    public function prepareAbsent(string $path, SaveContext $context): void
    {
        foreach ($this->allFields() as $field) {
            $field->prepareAbsent($path.'.*.'.$field->name(), $context);
        }
    }

    public function serialize(mixed $value): mixed
    {
        if (! is_iterable($value)) {
            return $value;
        }
        $blocks = [];
        foreach ($value as $block) {
            $fields = $this->blockFields(is_array($block) ? $block : (array) $block);
            $serialized = $this->types !== null ? ['type' => data_get($block, 'type')] : [];
            foreach ($fields as $field) {
                $serialized[$field->name()] = $field->serialize(data_get($block, $field->name()));
            }
            $blocks[] = $serialized;
        }

        return $blocks;
    }

    /**
     * The fields of every type of block.
     *
     * @return list<Field>
     */
    private function allFields(): array
    {
        if ($this->types === null) {
            return $this->fields;
        }

        return array_merge(...array_map(fn (BlockType $type): array => $type->getFields(), $this->types));
    }

    /**
     * @param  array<array-key, mixed>  $block
     * @return list<Field>
     */
    private function blockFields(array $block): array
    {
        if ($this->types === null) {
            return $this->fields;
        }
        foreach ($this->types as $type) {
            if ($type->id() === ($block['type'] ?? null)) {
                return $type->getFields();
            }
        }

        return [];
    }
}
