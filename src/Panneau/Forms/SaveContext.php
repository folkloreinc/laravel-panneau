<?php

namespace Panneau\Forms;

use Closure;
use Panneau\Uploads\Upload;
use Throwable;

/**
 * One save of an item, from its validated values to the repository. Fields that keep something
 * outside the item, such as the files of a `file` field, prepare their values with it
 * (`Field::prepare()`), and register what to do once the repository saved the item, or when
 * the save fails.
 */
final class SaveContext
{
    /**
     * @var list<Closure(object): void>
     */
    private array $afterSave = [];

    /**
     * @var list<Closure(): void>
     */
    private array $afterFailure = [];

    /**
     * @var array<string, mixed>
     */
    private array $shared = [];

    /**
     * @param  object|null  $item  The item being updated, as it was before this save. Null on create.
     * @param  (Closure(mixed): ?Upload)|null  $uploads  Resolves an upload reference for the user.
     */
    public function __construct(
        public readonly FormMode $mode = FormMode::Create,
        public readonly ?object $item = null,
        private readonly ?Closure $uploads = null,
    ) {}

    /**
     * The completed upload of the user that a value references (`{ "upload": "upl_…" }`), or
     * null.
     */
    public function upload(mixed $value): ?Upload
    {
        return $this->uploads !== null ? ($this->uploads)($value) : null;
    }

    /**
     * Runs a callback once the repository saved the item, with the saved item. The save can't
     * be undone then: an exception it throws is reported, and the other callbacks still run.
     *
     * @param  Closure(object): void  $callback
     */
    public function afterSave(Closure $callback): void
    {
        $this->afterSave[] = $callback;
    }

    /**
     * Runs a callback when the save fails after the values were prepared: the repository, or a
     * `creating` or `updating` hook, threw. It undoes what the preparation did outside the item.
     *
     * @param  Closure(): void  $callback
     */
    public function afterFailure(Closure $callback): void
    {
        $this->afterFailure[] = $callback;
    }

    /**
     * A value shared by the fields of this save, made the first time its key is asked: what a
     * field collects across the blocks of a repeater, for example.
     *
     * @template T
     *
     * @param  Closure(): T  $make
     * @return T
     */
    public function shared(string $key, Closure $make): mixed
    {
        if (! array_key_exists($key, $this->shared)) {
            $this->shared[$key] = $make();
        }

        /** @var T */
        return $this->shared[$key];
    }

    /**
     * Called by the controller once the repository saved the item.
     *
     * @internal
     */
    public function saved(object $item): void
    {
        $callbacks = $this->afterSave;
        $this->afterSave = $this->afterFailure = [];
        foreach ($callbacks as $callback) {
            self::run(fn () => $callback($item));
        }
    }

    /**
     * Called by the controller when the save failed.
     *
     * @internal
     */
    public function failed(): void
    {
        $callbacks = $this->afterFailure;
        $this->afterSave = $this->afterFailure = [];
        foreach ($callbacks as $callback) {
            self::run($callback);
        }
    }

    /**
     * Runs a callback and reports its exception: the save already succeeded or failed.
     */
    private static function run(Closure $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
