<?php

namespace Panneau\Mediatheque;

use Folklore\Image\Facades\Image;
use Folklore\Image\ImageManager;
use Folklore\Mediatheque\Contracts\Models\File as FileContract;
use Folklore\Mediatheque\Contracts\Models\Media as MediaContract;
use Folklore\Mediatheque\Contracts\Models\PipelineRun as PipelineRunContract;
use Folklore\Mediatheque\Enums\JobStatus;
use Folklore\Mediatheque\Enums\MediaStatus;
use Folklore\Mediatheque\Enums\PipelineStatus;
use Folklore\Mediatheque\Support\Models;
use Folklore\Mediatheque\Transcoders\TranscoderError;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Panneau\Fields\File;
use Panneau\Media\MediaTypes;
use Panneau\Resources\MediaResource;
use RuntimeException;
use Throwable;

/**
 * A media library on `folklore/laravel-mediatheque` 2: its items are the mediatheque's media,
 * through `MediathequeRepository`.
 *
 * - **Making a media** ingests the file (a direct upload, or a file of the request) with the
 *   library's scope as the media's attributes. The mediatheque detects its type from its
 *   content, stores it, and processes it in its queue.
 * - **Replacing the file** replaces the media's original with the mediatheque's
 *   `replaceOriginal()`: the media keeps its id, and is processed again.
 * - **The `media` of an item** comes from the mediatheque: its type, status (with the `error`
 *   of a failed media), original, main
 *   rendition, thumbnail, renditions (`variants`) and metadata. The thumbnail of an image is a
 *   `laravel-image` URL when `laravel-image` 2 is installed (`thumbnailPreset()`), and the
 *   mediatheque's URL otherwise.
 * - **Policies** are registered on the media model (`mediatheque.models.media`).
 *
 * Its fields are the media's attributes: `name`, and the columns the application adds.
 */
abstract class MediathequeResource extends MediaResource
{
    /**
     * The width of the thumbnails of images, without `thumbnailPreset()`.
     */
    protected const THUMBNAIL_WIDTH = 480;

    public function repository(): ?string
    {
        return MediathequeRepository::class;
    }

    public function itemClass(): ?string
    {
        return interface_exists(MediaContract::class) ? Models::media() : null;
    }

    /**
     * The preset of `laravel-image` for the thumbnails of images. Without one, thumbnails are
     * signed URLs of a width of 480 pixels.
     */
    protected function thumbnailPreset(): ?string
    {
        return null;
    }

    /**
     * @internal
     */
    protected function newFileField(): File
    {
        return MediathequeFile::make(self::FILE);
    }

    /**
     * The media's `MediaInfo`, from the mediatheque.
     */
    public function media(object $item): array
    {
        if (! $item instanceof MediaContract) {
            return parent::media($item);
        }
        $original = $item->original();
        $mime = $original?->mime() ?? $item->metadata('mime');
        $size = $original?->size() ?? $item->metadata('size');
        $media = [
            'type' => $item->type(),
            'mime' => is_string($mime) ? $mime : null,
            'size' => is_int($size) ? $size : null,
            'url' => self::url(fn (): ?string => $item->url()),
            'thumbnailUrl' => $this->thumbnailUrl($item),
        ];
        $variants = $this->variants($item);
        if (count($variants) > 0) {
            $media['variants'] = $variants;
        }
        foreach (['width', 'height', 'duration'] as $key) {
            $value = $item->metadata($key);
            if (is_int($value) || is_float($value)) {
                $media[$key] = $value;
            }
        }
        $media['status'] = $item->status()->value;
        if ($item->status() === MediaStatus::Failed) {
            $error = $this->failure($item);
            if ($error !== null) {
                $media['error'] = ['code' => $error->code->value, 'message' => $error->message];
            }
        }
        $metadata = array_filter($item->metadata(), fn (mixed $value): bool => $value !== null);
        if (count($metadata) > 0) {
            $media['metadata'] = $metadata;
        }

        return $media;
    }

    /**
     * Why the processing of a failed media failed: the error of its latest failed run (its probe,
     * or the pipeline whose main rendition failed), or of the rendition of that run that failed.
     * Null when the media model keeps no runs.
     */
    protected function failure(MediaContract $media): ?TranscoderError
    {
        if (! $media instanceof Model || ! method_exists($media, 'pipelineRuns')) {
            return null;
        }
        $runs = $media->pipelineRuns();
        if (! $runs instanceof Relation) {
            return null;
        }
        $run = $runs->getQuery()
            ->withoutGlobalScopes()
            ->where('status', PipelineStatus::Failed)
            ->latest('id')
            ->first();
        if (! $run instanceof PipelineRunContract) {
            return null;
        }
        if ($run->error() !== null) {
            return $run->error();
        }
        foreach ($run->jobs() as $job) {
            if ($job->status() === JobStatus::Failed && $job->error() !== null) {
                return $job->error();
            }
        }

        return null;
    }

    /**
     * The URL of the media's thumbnail: a `laravel-image` URL of its image when `laravel-image`
     * resolves it, or else the original of an image, or the poster or first thumbnail of other
     * types.
     */
    protected function thumbnailUrl(MediaContract $media): ?string
    {
        $image = $this->imageUrl($media);
        if ($image !== null) {
            return $image;
        }
        if ($media->type() === MediaTypes::IMAGE) {
            return self::url(fn (): ?string => $media->url(FileContract::ORIGINAL));
        }
        $thumbnail = $media->thumbnail();

        return $thumbnail !== null ? self::url(fn (): ?string => $thumbnail->url()) : null;
    }

    /**
     * The renditions that can be shown or played, by name: `{ "h264": { "url", "width",
     * "height" } }`.
     *
     * @return array<string, array{url: string, width?: int, height?: int}>
     */
    protected function variants(MediaContract $media): array
    {
        $variants = [];
        foreach ($media->files() as $file) {
            $handle = $file->handle();
            if ($handle === FileContract::ORIGINAL || $file->role() !== 'main' || isset($variants[$handle])) {
                continue;
            }
            $url = self::url(fn (): ?string => $file->url());
            if ($url === null) {
                continue;
            }
            $variants[$handle] = array_filter(
                ['url' => $url, 'width' => $file->width(), 'height' => $file->height()],
                fn (mixed $value): bool => $value !== null,
            );
        }

        return $variants;
    }

    /**
     * The MIME types of a type of the mediatheque (`mediatheque.types`), when they can all be
     * `accept` patterns.
     */
    protected function mimePatterns(string $type): ?array
    {
        $mimes = config('mediatheque.types.'.$type.'.mimes');
        if (! is_array($mimes) || count($mimes) === 0) {
            return null;
        }
        $patterns = [];
        foreach ($mimes as $mime) {
            if (! is_string($mime) || preg_match('#^[a-z0-9.+-]+/([a-z0-9.+-]+|\*)$#i', $mime) !== 1) {
                return null;
            }
            $patterns[] = $mime;
        }

        return $patterns;
    }

    /**
     * A `laravel-image` URL of the media's image, when `laravel-image` 2 is installed and its
     * `media` resolver resolves the media.
     */
    private function imageUrl(MediaContract $media): ?string
    {
        if (! class_exists(Image::class) || ! Container::getInstance()->bound(ImageManager::class)) {
            return null;
        }
        try {
            $image = Image::for($media);
            $preset = $this->thumbnailPreset();

            return $preset !== null ? $image->preset($preset)->url() : $image->width(static::THUMBNAIL_WIDTH)->signed()->url();
        } catch (Throwable) {
            // Not an image the resolver knows yet (no checksum, no image file), or no route
            return null;
        }
    }

    /**
     * @param  callable(): ?string  $url
     */
    private static function url(callable $url): ?string
    {
        try {
            return $url();
        } catch (RuntimeException) {
            // The disk gives no URL
            return null;
        }
    }
}
