<?php

namespace Panneau\Repositories\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Panneau\Fields\File;
use Panneau\Media\MediaTypes;
use Panneau\Resources\MediaResource;

/**
 * The repository of an Eloquent media library: the model keeps the stored file in a JSON
 * `file` column, and its media type in a `type` column, filled from the file's MIME type.
 *
 * A media whose type is outside the library's scope (its `mediaTypes()`) is refused when it is
 * made, with a `422` on `file`.
 */
trait HoldsMedia
{
    public function create(array $values, array $scope): object
    {
        if (array_key_exists(MediaResource::FILE, $values) && array_key_exists(MediaResource::TYPE, $scope)) {
            $allowed = is_array($scope[MediaResource::TYPE]) ? $scope[MediaResource::TYPE] : [$scope[MediaResource::TYPE]];
            if (! in_array($this->mediaType($values[MediaResource::FILE]), $allowed, true)) {
                throw ValidationException::withMessages([MediaResource::FILE => __('The :attribute must be a file of type: :types.', [
                    'attribute' => MediaResource::FILE,
                    'types' => implode(', ', array_filter($allowed, is_string(...))),
                ])]);
            }
        }

        return parent::create($values, $scope);
    }

    /**
     * Fills the model, and its `type` from the stored file when the values have one.
     *
     * @param  array<string, mixed>  $values
     */
    protected function fill(Model $model, array $values): void
    {
        parent::fill($model, $values);
        if (array_key_exists(MediaResource::FILE, $values)) {
            $model->setAttribute(MediaResource::TYPE, $this->mediaType($values[MediaResource::FILE]));
        }
    }

    /**
     * The media type of a stored file, from its MIME type.
     */
    protected function mediaType(mixed $file): string
    {
        return MediaTypes::fromMime(File::storedFiles($file)[0]['mime'] ?? null);
    }
}
