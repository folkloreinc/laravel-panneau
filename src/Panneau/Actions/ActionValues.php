<?php

namespace Panneau\Actions;

use Illuminate\Support\Arr;

/**
 * The validated parameters of an action.
 */
final readonly class ActionValues
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private array $values = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->values, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->values, $key);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
