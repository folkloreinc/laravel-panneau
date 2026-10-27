<?php

namespace Panneau\Fields;

use InvalidArgumentException;
use Panneau\Media\MediaTransform;

/**
 * Images of libraries, with an image editor (panneau-js decision 0031): a `media` field for
 * images. Its values, libraries, requests and rules are those of `Media`, and its `accept`
 * defaults to `image/*`.
 *
 * - `ratios()`: the ratios the editor suggests. The `crop()` rule enforces them.
 * - `rotate()` and `focalPoint()`: whether the editor offers rotation and flips, and the focal
 *   point. True by default.
 * - `transformBase()`: what a transform for one use starts from: the media's own transform
 *   (`media`, the default), the original file (`original`), or the user's choice in the editor
 *   (`choose`).
 */
class Image extends Media
{
    public const BASE_CHOOSE = 'choose';

    /**
     * @var list<string>|null
     */
    protected ?array $ratios = null;

    protected ?bool $rotate = null;

    protected ?bool $focalPoint = null;

    protected ?string $transformBase = null;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->accept('image/*');
    }

    public function type(): string
    {
        return 'image';
    }

    /**
     * The ratios the editor suggests: `16:9`, `1:1`…
     *
     * @param  array<array-key, string>  $ratios
     */
    public function ratios(array $ratios): static
    {
        $this->ratios = self::ratioList($ratios);

        return $this;
    }

    /**
     * Whether the editor offers rotation and flips.
     */
    public function rotate(bool $rotate = true): static
    {
        $this->rotate = $rotate;

        return $this;
    }

    /**
     * Whether the editor offers the focal point.
     */
    public function focalPoint(bool $focalPoint = true): static
    {
        $this->focalPoint = $focalPoint;

        return $this;
    }

    /**
     * What a transform for one use starts from: `media` (the media's own transform, the
     * default), `original` (the original file), or `choose` (the user chooses in the editor).
     */
    public function transformBase(string $base): static
    {
        if (! in_array($base, [MediaTransform::BASE_MEDIA, MediaTransform::BASE_ORIGINAL, self::BASE_CHOOSE], true)) {
            throw new InvalidArgumentException(sprintf('The transform base of an image field is media, original or choose, not "%s".', $base));
        }
        $this->transformBase = $base;

        return $this;
    }

    protected function typeDefinition(): array
    {
        return array_merge(parent::typeDefinition(), array_filter([
            'ratios' => $this->ratios,
            'rotate' => $this->rotate,
            'focalPoint' => $this->focalPoint,
            'transformBase' => $this->transformBase,
        ], fn (mixed $value): bool => $value !== null));
    }
}
