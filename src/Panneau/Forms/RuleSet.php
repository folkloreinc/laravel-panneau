<?php

namespace Panneau\Forms;

/**
 * Laravel validation rules, messages and attribute names, keyed by the dot path of a value.
 */
final class RuleSet
{
    /**
     * @var array<string, list<mixed>>
     */
    private array $rules = [];

    /**
     * @var array<string, string>
     */
    private array $messages = [];

    /**
     * @var array<string, string>
     */
    private array $attributes = [];

    /**
     * @param  list<mixed>  $rules
     */
    public function add(string $path, array $rules): void
    {
        $this->rules[$path] = [...($this->rules[$path] ?? []), ...$rules];
    }

    public function message(string $path, string $rule, string $message): void
    {
        $this->messages[$path.'.'.$rule] = $message;
    }

    public function attribute(string $path, string $name): void
    {
        $this->attributes[$path] = $name;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
