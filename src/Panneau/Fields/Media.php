<?php

namespace Panneau\Fields;

use Closure;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * One media, or a list with `multiple()`. A value is a media id, or a media object with an
 * `id`.
 */
class Media extends Field
{
    protected bool $multiple = false;

    /**
     * @var list<string>|null
     */
    protected ?array $accept = null;

    public function type(): string
    {
        return 'media';
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
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
     * Largest file size, in bytes.
     */
    public function maxSize(int $bytes, ?string $message = null): static
    {
        return $this->rule('maxSize', $bytes, $message);
    }

    protected function typeDefinition(): array
    {
        return array_filter([
            'multiple' => $this->multiple ?: null,
            'accept' => $this->accept,
        ], fn (mixed $value): bool => $value !== null);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return $this->multiple ? ['array'] : [$this->mediaRule()];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if ($this->multiple) {
            $rules->add($path.'.*', [$this->mediaRule()]);
        }
    }

    protected function toLaravelRule(array $rule, string $prefix): ?array
    {
        $value = $rule['value'] ?? null;
        $message = isset($rule['message']) && is_string($rule['message']) ? $rule['message'] : null;

        return match ($rule['rule']) {
            'accept' => is_array($value) ? ['accept', $this->acceptRule($value, $message)] : null,
            'maxSize' => is_int($value) ? ['maxSize', $this->maxSizeRule($value, $message)] : null,
            default => parent::toLaravelRule($rule, $prefix),
        };
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function mediaRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $valid = is_string($value) && $value !== ''
                || is_int($value)
                || is_array($value) && (is_string($value['id'] ?? null) || is_int($value['id'] ?? null));
            if (! $valid) {
                $fail(__('The :attribute must be a media.'));
            }
        };
    }

    /**
     * Checks the MIME type of media objects that have one, as the frontend does.
     *
     * @param  list<string>  $types
     * @return Closure(string, mixed, Closure): void
     */
    private function acceptRule(array $types, ?string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($types, $message): void {
            foreach (self::mediaObjects($value) as $media) {
                $mime = $media['mime'] ?? null;
                if (is_string($mime) && ! self::matchesMime($mime, $types)) {
                    $fail($message ?? __('The :attribute must be a file of type: :types.', ['types' => implode(', ', $types)]));

                    return;
                }
            }
        };
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function maxSizeRule(int $bytes, ?string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($bytes, $message): void {
            foreach (self::mediaObjects($value) as $media) {
                $size = $media['size'] ?? null;
                if ((is_int($size) || is_float($size)) && $size > $bytes) {
                    $fail($message ?? __('The :attribute must not be larger than :size bytes.', ['size' => $bytes]));

                    return;
                }
            }
        };
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private static function mediaObjects(mixed $value): array
    {
        $list = is_array($value) && array_is_list($value) ? $value : [$value];

        return array_values(array_filter($list, is_array(...)));
    }

    /**
     * @param  list<string>  $types
     */
    private static function matchesMime(string $mime, array $types): bool
    {
        foreach ($types as $type) {
            if (str_ends_with($type, '/*') ? str_starts_with($mime, substr($type, 0, -1)) : $mime === $type) {
                return true;
            }
        }

        return false;
    }
}
