<?php

namespace Panneau\Fields\Concerns;

/**
 * The `multiple` property and the `accept` and `maxSize` rules of fields that hold files:
 * `media` and `file`.
 */
trait AcceptsFiles
{
    protected bool $multiple = false;

    /**
     * @var list<string>|null
     */
    protected ?array $accept = null;

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Accepted MIME types: `image/*`, `application/pdf`…
     *
     * @param  string|array<array-key, string>  $types
     */
    public function accept(string|array $types, ?string $message = null): static
    {
        $this->accept = is_string($types) ? [$types] : array_values($types);

        return $this->rule('accept', $this->accept, $message);
    }

    /**
     * Whether `accept()` restricts the MIME types.
     */
    public function hasAccept(): bool
    {
        return $this->accept !== null;
    }

    /**
     * Largest file size, in bytes.
     */
    public function maxSize(int $bytes, ?string $message = null): static
    {
        return $this->rule('maxSize', $bytes, $message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeDefinition(): array
    {
        return array_filter([
            'multiple' => $this->multiple ?: null,
            'accept' => $this->accept,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Whether a MIME type matches one of the accepted types (`image/*` matches `image/png`).
     *
     * @param  list<string>  $types
     */
    protected static function matchesMime(string $mime, array $types): bool
    {
        foreach ($types as $type) {
            if (str_ends_with($type, '/*') ? str_starts_with($mime, substr($type, 0, -1)) : $mime === $type) {
                return true;
            }
        }

        return false;
    }
}
