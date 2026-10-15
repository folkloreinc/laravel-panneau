<?php

namespace Panneau\Forms;

/**
 * A condition on form values, for `visibleWhen`: compares one field (`equals`, `in`, `notIn`,
 * `filled`), or combines conditions with `all` or `any`. Paths are relative to the values of
 * the form, or of the group or block that holds the field.
 */
final class Condition
{
    private ?string $field = null;

    private bool $hasEquals = false;

    private mixed $equals = null;

    /**
     * @var list<mixed>|null
     */
    private ?array $in = null;

    /**
     * @var list<mixed>|null
     */
    private ?array $notIn = null;

    private ?bool $filled = null;

    /**
     * @var list<self>|null
     */
    private ?array $all = null;

    /**
     * @var list<self>|null
     */
    private ?array $any = null;

    /**
     * A condition on a field, or a dot path.
     */
    public static function field(string $field): self
    {
        $condition = new self;
        $condition->field = $field;

        return $condition;
    }

    /**
     * True when every condition is.
     *
     * @param  list<self>  $conditions
     */
    public static function all(array $conditions): self
    {
        $condition = new self;
        $condition->all = $conditions;

        return $condition;
    }

    /**
     * True when one of the conditions is.
     *
     * @param  list<self>  $conditions
     */
    public static function any(array $conditions): self
    {
        $condition = new self;
        $condition->any = $conditions;

        return $condition;
    }

    /**
     * Builds a condition from its protocol form.
     *
     * @param  array<string, mixed>  $condition
     */
    public static function fromArray(array $condition): self
    {
        $instance = new self;
        $instance->field = isset($condition['field']) && is_string($condition['field']) ? $condition['field'] : null;
        if (array_key_exists('equals', $condition)) {
            $instance->hasEquals = true;
            $instance->equals = $condition['equals'];
        }
        $instance->in = isset($condition['in']) && is_array($condition['in']) ? array_values($condition['in']) : null;
        $instance->notIn = isset($condition['notIn']) && is_array($condition['notIn']) ? array_values($condition['notIn']) : null;
        $instance->filled = isset($condition['filled']) && is_bool($condition['filled']) ? $condition['filled'] : null;
        foreach (['all', 'any'] as $key) {
            if (isset($condition[$key]) && is_array($condition[$key])) {
                $instance->{$key} = array_values(array_map(
                    fn (mixed $child): self => $child instanceof self ? $child : self::fromArray((array) $child),
                    $condition[$key],
                ));
            }
        }

        return $instance;
    }

    public function equals(mixed $value): self
    {
        $this->hasEquals = true;
        $this->equals = $value;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function in(array $values): self
    {
        $this->in = array_values($values);

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function notIn(array $values): self
    {
        $this->notIn = array_values($values);

        return $this;
    }

    public function filled(bool $filled = true): self
    {
        $this->filled = $filled;

        return $this;
    }

    /**
     * Evaluates the condition against form values, as the protocol's `evaluateCondition()`
     * does.
     *
     * @param  array<array-key, mixed>  $values
     */
    public function evaluate(array $values): bool
    {
        if ($this->all !== null) {
            foreach ($this->all as $condition) {
                if (! $condition->evaluate($values)) {
                    return false;
                }
            }
        }
        if ($this->any !== null) {
            $matches = false;
            foreach ($this->any as $condition) {
                if ($condition->evaluate($values)) {
                    $matches = true;
                    break;
                }
            }
            if (! $matches) {
                return false;
            }
        }
        if ($this->field === null) {
            return true;
        }

        $value = Values::get($values, $this->field);
        if ($this->hasEquals && ! Values::equal($value, $this->equals)) {
            return false;
        }
        if ($this->in !== null && ! $this->contains($this->in, $value)) {
            return false;
        }
        if ($this->notIn !== null && $this->contains($this->notIn, $value)) {
            return false;
        }

        return $this->filled === null || Values::filled($value) === $this->filled;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $condition = [];
        if ($this->field !== null) {
            $condition['field'] = $this->field;
        }
        if ($this->hasEquals) {
            $condition['equals'] = $this->equals;
        }
        if ($this->in !== null) {
            $condition['in'] = $this->in;
        }
        if ($this->notIn !== null) {
            $condition['notIn'] = $this->notIn;
        }
        if ($this->filled !== null) {
            $condition['filled'] = $this->filled;
        }
        if ($this->all !== null) {
            $condition['all'] = array_map(fn (self $child): array => $child->toArray(), $this->all);
        }
        if ($this->any !== null) {
            $condition['any'] = array_map(fn (self $child): array => $child->toArray(), $this->any);
        }

        return $condition;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private function contains(array $candidates, mixed $value): bool
    {
        foreach ($candidates as $candidate) {
            if (Values::equal($value, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
