<?php

namespace Panneau\Mediatheque;

use Folklore\Mediatheque\Contracts\Mediatheque;
use Folklore\Mediatheque\Contracts\Models\Media as MediaContract;
use Folklore\Mediatheque\Exceptions\FileRejected;
use Folklore\Mediatheque\Ingest\Source;
use Folklore\Mediatheque\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Panneau\Repositories\SoftDeletingEloquentRepository;
use Panneau\Resources\MediaResource;
use Throwable;

/**
 * The repository of a library on `laravel-mediatheque` 2: its items are the mediatheque's media,
 * of the model set in `mediatheque.models.media`.
 *
 * - **Making a media** ingests the file of the `file` value with `Mediatheque::ingest()`, with
 *   the scalar values of the scope as the media's attributes (a tenant column, for example), and
 *   the `name` value as its name. The other values are then set like `EloquentRepository` does.
 *   A file the mediatheque refuses, or of a type outside the scope's `type`, is a `422` on
 *   `file`, and leaves neither a row nor a file.
 * - **The file of a media can't be replaced**: the mediatheque has no public API for it yet.
 *   The library's `file` field is `createOnly()`.
 * - Lists, filters (`type`), search, scope, trash and restore are those of the Eloquent model.
 *   Media can't be duplicated.
 *
 * Extend it to change how files are ingested (`ingest()`: visibility, pipeline), and return
 * the subclass from the library's `repository()`.
 *
 * @extends SoftDeletingEloquentRepository<Model&MediaContract>
 */
class MediathequeRepository extends SoftDeletingEloquentRepository
{
    /**
     * @param  class-string<Model&MediaContract>|null  $model  The media model. Defaults to the
     *                                                         mediatheque's `models.media`.
     */
    public function __construct(?string $model = null)
    {
        if (! interface_exists(Mediatheque::class)) {
            throw new LogicException('A media library on the mediatheque needs folklore/laravel-mediatheque ^2.0.');
        }
        parent::__construct($model ?? Models::media());
    }

    public function create(array $values, array $scope): object
    {
        $source = $values[MediaResource::FILE] ?? null;
        if (! $source instanceof Source) {
            throw ValidationException::withMessages([MediaResource::FILE => __('The :attribute field is required.', ['attribute' => MediaResource::FILE])]);
        }
        unset($values[MediaResource::FILE]);
        $name = is_string($values['name'] ?? null) && $values['name'] !== '' ? $values['name'] : null;
        if ($name === null) {
            // The media is named after its file
            unset($values['name']);
        }
        $attributes = array_filter($scope, fn (mixed $value): bool => ! self::isConstraint($value));
        $constraints = array_filter($scope, self::isConstraint(...));

        $original = null;
        try {
            return $this->newModel()->getConnection()->transaction(function () use ($source, $name, $attributes, $constraints, $values, &$original): Model {
                $model = $this->ensureModel($this->ingest($source, $name, $attributes));
                $original = $model->original();
                $this->checkConstraints($model, $constraints);
                if (count($values) > 0) {
                    $this->fill($model, $values);
                    $model->save();
                    $this->syncRelations($model, $values);
                }

                return $model->refresh()->load('mediaFiles');
            });
        } catch (FileRejected $exception) {
            throw ValidationException::withMessages([MediaResource::FILE => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // The rows are rolled back; the original was stored before them.
            if ($original !== null && $original->source() !== null && $original->path() !== null) {
                Storage::disk($original->source())->delete($original->path());
            }

            throw $exception;
        }
    }

    public function update(object $item, array $values): object
    {
        if (array_key_exists(MediaResource::FILE, $values)) {
            throw ValidationException::withMessages([MediaResource::FILE => __('The file of a media can\'t be replaced.')]);
        }

        return parent::update($item, $values);
    }

    public function duplicate(object $item, array $scope): object
    {
        throw new LogicException('A media of the mediatheque can\'t be duplicated.');
    }

    /**
     * Ingests a file. Override it to choose the visibility or the pipeline of the media.
     *
     * @param  array<string, mixed>  $attributes  The scalar values of the scope.
     */
    protected function ingest(Source $source, ?string $name, array $attributes): MediaContract
    {
        return app(Mediatheque::class)->ingest($source, name: $name, attributes: $attributes);
    }

    /**
     * The media and their files, in one query.
     */
    protected function scoped(array $scope): Builder
    {
        return parent::scoped($scope)->with('mediaFiles');
    }

    /**
     * Refuses a media outside the lists of the scope, such as its media types.
     *
     * @param  array<string, list<mixed>>  $constraints
     */
    private function checkConstraints(Model $model, array $constraints): void
    {
        foreach ($constraints as $column => $allowed) {
            $value = $model->getAttribute($column);
            if (! in_array($value instanceof \BackedEnum ? $value->value : $value, $allowed, true)) {
                throw ValidationException::withMessages([MediaResource::FILE => $column === MediaResource::TYPE
                    ? __('The :attribute must be a file of type: :types.', ['attribute' => MediaResource::FILE, 'types' => implode(', ', array_filter($allowed, is_string(...)))])
                    : __('The :attribute is outside this library.', ['attribute' => MediaResource::FILE])]);
            }
        }
    }
}
