<?php

namespace Panneau\Fields\Files;

use Illuminate\Support\Facades\Storage;

/**
 * The stored files of a file field in the item before a save, and those the save keeps. Once the
 * item is saved, the others are deleted from their disk.
 *
 * @internal
 */
final class FileRemovals
{
    /**
     * @var array<string, true>
     */
    private array $kept = [];

    /**
     * @param  list<array{disk: string, path: string, name: string, mime: string|null, size: int|null}>  $candidates
     */
    public function __construct(public readonly array $candidates) {}

    /**
     * @param  array{disk: string, path: string}  $file
     */
    public function keep(array $file): void
    {
        $this->kept[self::key($file)] = true;
    }

    /**
     * Deletes the files that the save didn't keep.
     */
    public function delete(): void
    {
        $deleted = [];
        foreach ($this->candidates as $file) {
            $key = self::key($file);
            if (! isset($this->kept[$key]) && ! isset($deleted[$key])) {
                Storage::disk($file['disk'])->delete($file['path']);
                $deleted[$key] = true;
            }
        }
    }

    /**
     * @param  array{disk: string, path: string}  $file
     */
    private static function key(array $file): string
    {
        return $file['disk']."\0".$file['path'];
    }
}
