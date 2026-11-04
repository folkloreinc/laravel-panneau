<?php

namespace Panneau\Fields;

use Illuminate\Validation\Rule as LaravelRule;
use InvalidArgumentException;
use Panneau\Forms\ValidationContext;

/**
 * A color: a hexadecimal value (`#1d4ed8`), or, with `palette()`, the `value` of one of its colors.
 */
class Color extends Text
{
    private const HEX = '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    public function type(): string
    {
        return 'color';
    }

    /**
     * The colors to pick from (`settings.palette`): the field only offers them, and the server
     * refuses other values. Each color is `['value' => 'purple', 'label' => 'Purple', 'color' =>
     * '#6b21a8']`: `value` is what the field stores, `color` (hexadecimal) draws its swatch, and
     * `label` names it. Without `color`, `value` must be a hexadecimal color.
     *
     * Storing names rather than hexadecimal colors lets a site change its brand's colors without
     * rewriting its content.
     *
     * @param  array<array-key, mixed>  $colors
     *
     * @throws InvalidArgumentException When a color isn't of that shape, or a value is repeated.
     */
    public function palette(array $colors): static
    {
        $palette = [];
        foreach ($colors as $index => $color) {
            $palette[] = self::paletteColor($color, $index);
        }
        $values = array_column($palette, 'value');
        if (count($palette) === 0 || count(array_unique($values)) !== count($values)) {
            throw new InvalidArgumentException('A palette needs at least one color, and distinct values.');
        }

        return $this->settings(['palette' => $palette]);
    }

    /**
     * The values of the palette, or null without one.
     *
     * @return list<string>|null
     */
    public function paletteValues(): ?array
    {
        $palette = $this->settings['palette'] ?? null;
        if (! is_array($palette)) {
            return null;
        }
        $values = [];
        foreach ($palette as $color) {
            if (is_array($color) && is_string($color['value'] ?? null)) {
                $values[] = $color['value'];
            }
        }

        return $values;
    }

    /**
     * Checks a color of a palette.
     *
     * @return array{value: string, label?: string, color?: string}
     */
    private static function paletteColor(mixed $color, int|string $index): array
    {
        $value = is_array($color) ? ($color['value'] ?? null) : null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('The color %s of the palette needs a value.', var_export($index, true)));
        }
        /** @var array<array-key, mixed> $color */
        $unknown = array_diff(array_keys($color), ['value', 'label', 'color']);
        if (count($unknown) > 0) {
            throw new InvalidArgumentException(sprintf('The color %s of the palette has unknown keys: %s.', var_export($value, true), implode(', ', $unknown)));
        }
        $checked = ['value' => $value];
        if (array_key_exists('label', $color)) {
            if (! is_string($color['label'])) {
                throw new InvalidArgumentException(sprintf('The label of the color %s must be a string.', var_export($value, true)));
            }
            $checked['label'] = $color['label'];
        }
        if (array_key_exists('color', $color)) {
            if (! is_string($color['color']) || preg_match(self::HEX, $color['color']) !== 1) {
                throw new InvalidArgumentException(sprintf('The color of %s must be a hexadecimal color (#6b21a8).', var_export($value, true)));
            }
            $checked['color'] = $color['color'];
        } elseif (preg_match(self::HEX, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('The color %s of the palette needs a hexadecimal color, since its value is not one.', var_export($value, true)));
        }

        return $checked;
    }

    protected function valueRules(ValidationContext $context): array
    {
        $values = $this->paletteValues();

        return $values !== null ? ['string', LaravelRule::in($values)] : parent::valueRules($context);
    }
}
