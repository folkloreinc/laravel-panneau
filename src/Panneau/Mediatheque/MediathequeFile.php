<?php

namespace Panneau\Mediatheque;

use Folklore\Mediatheque\Contracts\Models\Media as MediaContract;
use Folklore\Mediatheque\Ingest\Source;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Panneau\Fields\File;
use Panneau\Forms\SaveContext;
use RuntimeException;

/**
 * The `file` field of a library on `laravel-mediatheque`: the original of a media.
 *
 * The field doesn't store the file: it gives the repository the source that the mediatheque
 * ingests, or that replaces the original (`Source::disk()` for a direct upload, which the
 * mediatheque copies on the storage itself, or the file of the request). The original sent back
 * as the item gave it keeps it (`KeptOriginal`). Its disk, directory and visibility are the
 * mediatheque's. Items give the original as a `StoredFile`, with the mediatheque's URL.
 *
 * @internal Configured through `MediathequeResource::file()`.
 */
class MediathequeFile extends File
{
    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof UploadedFile) {
            return Source::upload($value);
        }
        if (is_array($value) && array_key_exists('upload', $value)) {
            $upload = $context->upload($value);
            if ($upload === null) {
                throw ValidationException::withMessages([$path => __('The upload of the :attribute does not exist.', ['attribute' => $this->name])]);
            }
            $name = $upload->name ?? (is_string($value['name'] ?? null) ? $value['name'] : $upload->uid);

            return Source::disk($upload->disk, $upload->path, $name);
        }
        if (is_array($value)) {
            // The original the item gave, sent back: the validation found it among the item's files
            return new KeptOriginal;
        }

        throw ValidationException::withMessages([$path => __('The :attribute is not a new file.', ['attribute' => $this->name])]);
    }

    public function prepareAbsent(string $path, SaveContext $context): void {}

    public function resolve(object $item): mixed
    {
        if (! $item instanceof MediaContract || $this->resolveUsing !== null) {
            return parent::resolve($item);
        }
        $original = $item->original();
        if ($original === null) {
            return null;
        }
        try {
            $url = $original->url();
        } catch (RuntimeException) {
            $url = null;
        }

        return [
            'name' => self::originalName($item),
            'url' => $url,
            'mime' => $original->mime(),
            'size' => $original->size(),
        ];
    }

    protected function currentValue(?object $item, string $path): mixed
    {
        if (! $item instanceof MediaContract || $path !== $this->name) {
            return parent::currentValue($item, $path);
        }
        $original = $item->original();
        if ($original === null || $original->source() === null || $original->path() === null) {
            return null;
        }

        return [
            'disk' => $original->source(),
            'path' => $original->path(),
            'name' => self::originalName($item),
            'mime' => $original->mime(),
            'size' => $original->size(),
        ];
    }

    /**
     * The name the original was ingested with.
     */
    private static function originalName(MediaContract $media): string
    {
        $filename = $media->metadata('filename');
        if (is_string($filename) && $filename !== '') {
            return $filename;
        }
        $path = $media->original()?->path();

        return $path !== null ? basename($path) : ($media->name() ?? 'file');
    }
}
