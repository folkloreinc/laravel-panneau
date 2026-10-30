<?php

namespace Panneau\Upgrade;

use Illuminate\Filesystem\Filesystem;

/**
 * Copies of the files that the upgrade changes: `{file}.1x.bak`, then `{file}.1x.bak.2`… A
 * backup is never overwritten.
 *
 * @internal
 */
final class Backups
{
    public const SUFFIX = '.1x.bak';

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Copies a file before it changes, unless a backup already has its contents.
     *
     * @return string The backup that has its contents.
     */
    public function backup(string $path): string
    {
        $contents = (string) $this->files->get($path);
        for ($number = 1; ; $number++) {
            $backup = $path.self::SUFFIX.($number === 1 ? '' : '.'.$number);
            if (! $this->files->exists($backup)) {
                $this->files->put($backup, $contents);

                return $backup;
            }
            if ((string) $this->files->get($backup) === $contents) {
                return $backup;
            }
        }
    }

    /**
     * Whether the first backup of a file exists, with other contents than the file.
     */
    public function differs(string $path): bool
    {
        $backup = $path.self::SUFFIX;

        return $this->files->exists($backup) && (string) $this->files->get($backup) !== (string) $this->files->get($path);
    }
}
