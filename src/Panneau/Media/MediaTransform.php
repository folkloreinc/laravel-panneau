<?php

namespace Panneau\Media;

/**
 * The protocol's `MediaTransform`: the non-destructive edits of an image, applied in this order:
 * crop, then rotate, then flip.
 *
 * ```json
 * { "crop": { "x": 120, "y": 0, "width": 1600, "height": 900 }, "rotate": 90, "flipX": true, "focalPoint": { "x": 0.42, "y": 0.35 } }
 * ```
 *
 * - `crop`: whole pixels of the upright original, from its top left corner;
 * - `rotate`: 0, 90, 180 or 270 degrees, clockwise;
 * - `flipX`, `flipY`: mirrors left to right, top to bottom;
 * - `focalPoint`: from 0 to 1, relative to the upright original;
 * - `base`: what a transform for one use starts from: the original file (`original`, the
 *   default), or the media's own transform (`media`), whose version its crop and focal point
 *   are then relative to (panneau-js decision 0031);
 * - `baseTransform`: with `base: "media"`, the media's own transform it was made on, itself a
 *   transform without a base;
 * - other keys, which editor tools can add, are kept as they are: renderers ignore the ones they
 *   don't know.
 */
final class MediaTransform
{
    public const BASE_MEDIA = 'media';

    public const BASE_ORIGINAL = 'original';

    private const ROTATIONS = [0, 90, 180, 270];

    /**
     * Why a value is not a transform, or null when it is one (or null).
     */
    public static function error(mixed $value): ?string
    {
        return self::check($value, true);
    }

    /**
     * A valid transform, with its known properties normalized (whole pixels, no rotation of 0,
     * no false flips) and the others kept as they are, in their order. Null when it is invalid
     * or has nothing.
     *
     * @return array<string, mixed>|null
     */
    public static function normalize(mixed $value): ?array
    {
        if (! is_array($value) || self::error($value) !== null) {
            return null;
        }

        return self::normalized($value);
    }

    /**
     * The size of the crop of a transform once rendered: a quarter turn swaps its width and its
     * height. Null without a crop.
     *
     * @return array{width: int, height: int}|null
     */
    public static function renderedCropSize(mixed $value): ?array
    {
        if (! is_array($value) || ! is_array($value['crop'] ?? null)) {
            return null;
        }
        $width = self::whole($value['crop']['width'] ?? 0);
        $height = self::whole($value['crop']['height'] ?? 0);
        $rotate = self::whole($value['rotate'] ?? 0);

        return $rotate === 90 || $rotate === 270 ? ['width' => $height, 'height' => $width] : ['width' => $width, 'height' => $height];
    }

    /**
     * The value of a ratio (`16:9` gives 1.777…), or null when it isn't one.
     */
    public static function parseRatio(string $ratio): ?float
    {
        if (preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', $ratio, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] / (int) $matches[2];
    }

    /**
     * Whether a size has a ratio: its height is within one pixel of its width divided by the
     * ratio, since crops are in whole pixels.
     */
    public static function matchesRatio(int $width, int $height, string $ratio): bool
    {
        $value = self::parseRatio($ratio);

        return $value !== null && abs($width / $value - $height) <= 1;
    }

    private static function check(mixed $value, bool $allowsBase): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value) || (array_is_list($value) && count($value) > 0)) {
            return 'must be an object';
        }
        $crop = $value['crop'] ?? null;
        if ($crop !== null) {
            if (! is_array($crop)
                || ! self::isWhole($crop['x'] ?? null, 0) || ! self::isWhole($crop['y'] ?? null, 0)
                || ! self::isWhole($crop['width'] ?? null, 1) || ! self::isWhole($crop['height'] ?? null, 1)) {
                return 'crop must have a whole x and y from 0, and a whole width and height from 1';
            }
        }
        $rotate = $value['rotate'] ?? null;
        if ($rotate !== null && ! (self::isWhole($rotate, 0) && in_array((int) $rotate, self::ROTATIONS, true))) {
            return 'rotate must be 0, 90, 180 or 270';
        }
        foreach (['flipX', 'flipY'] as $flip) {
            if (($value[$flip] ?? null) !== null && ! is_bool($value[$flip])) {
                return $flip.' must be true or false';
            }
        }
        $focalPoint = $value['focalPoint'] ?? null;
        if ($focalPoint !== null) {
            if (! is_array($focalPoint) || ! self::isRatio($focalPoint['x'] ?? null) || ! self::isRatio($focalPoint['y'] ?? null)) {
                return 'focalPoint must have an x and a y from 0 to 1';
            }
        }
        if (! $allowsBase) {
            return array_key_exists('base', $value) || array_key_exists('baseTransform', $value)
                ? 'baseTransform must be a transform without a base'
                : null;
        }
        $base = $value['base'] ?? null;
        if ($base !== null && ! in_array($base, [self::BASE_MEDIA, self::BASE_ORIGINAL], true)) {
            return 'base must be media or original';
        }
        $baseTransform = $value['baseTransform'] ?? null;
        if ($baseTransform !== null && ($error = self::check($baseTransform, false)) !== null) {
            return str_starts_with($error, 'baseTransform') ? $error : 'baseTransform: '.$error;
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $value  A valid transform.
     * @return array<string, mixed>|null
     */
    private static function normalized(array $value): ?array
    {
        $transform = [];
        foreach ($value as $key => $property) {
            $key = (string) $key;
            if ($property === null) {
                continue;
            }
            switch ($key) {
                case 'crop':
                    $transform['crop'] = [
                        'x' => self::whole($property['x'] ?? 0),
                        'y' => self::whole($property['y'] ?? 0),
                        'width' => self::whole($property['width'] ?? 1),
                        'height' => self::whole($property['height'] ?? 1),
                    ];
                    break;
                case 'rotate':
                    if (self::whole($property) !== 0) {
                        $transform['rotate'] = self::whole($property);
                    }
                    break;
                case 'flipX':
                case 'flipY':
                    if ($property === true) {
                        $transform[$key] = true;
                    }
                    break;
                case 'focalPoint':
                    $x = $property['x'] ?? 0;
                    $y = $property['y'] ?? 0;
                    $transform['focalPoint'] = [
                        'x' => is_int($x) || is_float($x) ? $x : 0,
                        'y' => is_int($y) || is_float($y) ? $y : 0,
                    ];
                    break;
                case 'baseTransform':
                    $base = is_array($property) ? self::normalized($property) : null;
                    if ($base !== null) {
                        $transform['baseTransform'] = $base;
                    }
                    break;
                default:
                    // `base`, and the keys of editor tools, as they are
                    $transform[$key] = $property;
            }
        }

        return count($transform) > 0 ? $transform : null;
    }

    private static function isWhole(mixed $value, int $min): bool
    {
        return (is_int($value) || is_float($value) && floor($value) === $value && is_finite($value)) && $value >= $min;
    }

    private static function isRatio(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && $value >= 0 && $value <= 1;
    }

    private static function whole(mixed $value): int
    {
        return is_int($value) || is_float($value) ? (int) $value : 0;
    }
}
