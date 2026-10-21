<?php

namespace Panneau\Actions;

use Closure;
use Illuminate\Support\Arr;
use Panneau\Uploads\Upload;

/**
 * The validated parameters of an action.
 */
final readonly class ActionValues
{
    /**
     * @param  array<string, mixed>  $values
     * @param  (Closure(mixed): ?Upload)|null  $uploads  Resolves an upload reference for the user.
     */
    public function __construct(
        private array $values = [],
        private ?Closure $uploads = null,
    ) {}

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
     * The completed upload that a parameter references (`{ "upload": "upl_…" }`), such as the
     * file of an import, or null. Read it with `readStream()`.
     */
    public function upload(string $key): ?Upload
    {
        return $this->uploads !== null ? ($this->uploads)($this->get($key)) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
