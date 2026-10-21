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
 * - `focalPoint`: from 0 to 1, relative to the upright original.
 */
final class MediaTransform
{
    private const ROTATIONS = [0, 90, 180, 270];

    /**
     * Why a value is not a transform, or null when it is one (or null).
     */
    public static function error(mixed $value): ?string
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

        return null;
    }

    /**
     * A valid transform with only the properties of the protocol, or null when it has none.
     *
     * @return array{crop?: array{x: int, y: int, width: int, height: int}, rotate?: int, flipX?: bool, flipY?: bool, focalPoint?: array{x: float|int, y: float|int}}|null
     */
    public static function normalize(mixed $value): ?array
    {
        if (! is_array($value) || self::error($value) !== null) {
            return null;
        }
        $transform = [];
        if (is_array($value['crop'] ?? null)) {
            $crop = $value['crop'];
            $transform['crop'] = [
                'x' => self::whole($crop['x'] ?? 0),
                'y' => self::whole($crop['y'] ?? 0),
                'width' => self::whole($crop['width'] ?? 1),
                'height' => self::whole($crop['height'] ?? 1),
            ];
        }
        if (isset($value['rotate']) && self::whole($value['rotate']) !== 0) {
            $transform['rotate'] = self::whole($value['rotate']);
        }
        foreach (['flipX', 'flipY'] as $flip) {
            if (($value[$flip] ?? null) === true) {
                $transform[$flip] = true;
            }
        }
        if (is_array($value['focalPoint'] ?? null)) {
            $x = $value['focalPoint']['x'] ?? 0;
            $y = $value['focalPoint']['y'] ?? 0;
            $transform['focalPoint'] = [
                'x' => is_int($x) || is_float($x) ? $x : 0,
                'y' => is_int($y) || is_float($y) ? $y : 0,
            ];
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
