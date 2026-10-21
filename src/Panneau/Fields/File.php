<?php

namespace Panneau\Fields;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Panneau\Fields\Concerns\AcceptsFiles;
use Panneau\Fields\Files\FileRemovals;
use Panneau\Forms\RuleSet;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;
use Panneau\Uploads\Upload;
use RuntimeException;

/**
 * Files that belong to the item, outside the media library: an attachment, a PDF. One file, or
 * a list with `multiple()`.
 *
 * - **Requests** send `{ "upload": "upl_…" }` for a direct upload of the user, a file in
 *   `multipart/form-data`, the stored file the item gave to keep it, or `null` to remove it.
 *   `accept()` and `maxSize()` are checked on the file received: the MIME type and size of the
 *   upload, as recorded when it completed, or those detected for a file in the request.
 * - **Saving** copies each new file to `{directory}/{ulid}/{name}` on the field's disk, and gives
 *   the repository `{ "disk", "path", "name", "mime", "size" }` (a list with `multiple()`). A
 *   stored file sent back keeps the item's own stored value: paths and URLs from the client are
 *   never used. Files replaced or removed are deleted once the item is saved; new files are
 *   deleted if the save fails.
 * - **Items** give `{ "name", "url", "mime", "size" }`. Public files get the disk's URL, private
 *   files a temporary URL (`panneau.files.url_ttl`), and `url` is null when the disk can give
 *   neither.
 *
 * With `EloquentRepository`, the attribute needs a JSON column with the `array` cast.
 */
class File extends Field
{
    use AcceptsFiles;

    public const PUBLIC = 'public';

    public const PRIVATE = 'private';

    protected ?string $disk = null;

    protected string $directory = 'files';

    protected string $visibility = self::PRIVATE;

    public function type(): string
    {
        return 'file';
    }

    /**
     * The disk where files are stored. Defaults to the app's default disk.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): string
    {
        return $this->disk ?? (string) config('filesystems.default');
    }

    /**
     * The directory of the files on the disk. Each file goes in its own sub-directory:
     * `{directory}/{ulid}/{name}`.
     */
    public function directory(string $directory): static
    {
        $this->directory = trim($directory, '/');

        return $this;
    }

    /**
     * `public` files get the disk's URL; `private` files (the default) get a temporary URL.
     */
    public function visibility(string $visibility): static
    {
        if (! in_array($visibility, [self::PUBLIC, self::PRIVATE], true)) {
            throw new LogicException(sprintf('The visibility of a file field is "public" or "private", not "%s".', $visibility));
        }
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * File fields are not localized: a file holds its own language.
     */
    public function localized(bool $localized = true): static
    {
        if ($localized) {
            throw new LogicException(sprintf('The file field "%s" can\'t be localized.', $this->name));
        }

        return $this;
    }

    protected function valueRules(ValidationContext $context): array
    {
        return $this->multiple ? ['array', 'list'] : [];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        $candidates = fn (): array => self::storedFiles($this->currentValue($context->item, $path));
        $fileRules = [$this->fileRule($context, $candidates), ...$this->receivedFileRules($context)];
        if ($this->multiple) {
            $rules->add($path.'.*', ['required', ...$fileRules]);
        } else {
            $rules->add($path, $fileRules);
        }
    }

    /**
     * `accept` and `maxSize` are checked on each file received, by `collectNestedRules()`.
     */
    protected function toLaravelRule(array $rule, string $prefix): ?array
    {
        return in_array($rule['rule'], ['accept', 'maxSize'], true) ? null : parent::toLaravelRule($rule, $prefix);
    }

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        $removals = $this->removals($path, $context);
        if (! $this->multiple) {
            return $this->store($value, $path, null, $removals, $context);
        }
        $stored = [];
        foreach (is_array($value) ? array_values($value) : [] as $index => $entry) {
            $file = $this->store($entry, $path.'.'.$index, $index, $removals, $context);
            if ($file !== null) {
                $stored[] = $file;
            }
        }

        return $stored;
    }

    public function prepareAbsent(string $path, SaveContext $context): void
    {
        $this->removals($path, $context);
    }

    /**
     * Turns a stored value into the protocol's stored files: `{ name, url, mime, size }`.
     */
    protected function serializeValue(mixed $value): mixed
    {
        $files = array_map($this->toStoredFile(...), self::storedFiles($value));
        if ($this->multiple) {
            return $files;
        }

        return $files[0] ?? null;
    }

    /**
     * A stored file as the protocol's `StoredFile`.
     *
     * @param  array{disk: string, path: string, name: string, mime: string|null, size: int|null}  $file
     * @return array{name: string, url: string|null, mime: string|null, size: int|null}
     */
    public function toStoredFile(array $file): array
    {
        return [
            'name' => $file['name'],
            'url' => $this->url($file),
            'mime' => $file['mime'],
            'size' => $file['size'],
        ];
    }

    /**
     * The URL of a stored file: the disk's URL for a public file, a temporary URL for a private
     * one, or null when the disk gives neither.
     *
     * @param  array{disk: string, path: string}  $file
     */
    public function url(array $file): ?string
    {
        $disk = Storage::disk($file['disk']);
        try {
            if ($this->visibility === self::PUBLIC) {
                return $disk->url($file['path']);
            }
            if ($disk->providesTemporaryUrls()) {
                $ttl = max(1, (int) config('panneau.files.url_ttl', 3600));

                return $disk->temporaryUrl($file['path'], Carbon::now()->addSeconds($ttl));
            }
        } catch (RuntimeException) {
            // The driver gives no URL.
        }

        return null;
    }

    /**
     * The stored value of the field in an item, at a path. In a list of blocks, the indices of
     * the blocks become `*`, so the files of every block are found.
     */
    protected function currentValue(?object $item, string $path): mixed
    {
        if ($item === null) {
            return null;
        }
        if ($path === $this->name && $this->resolveUsing !== null) {
            return ($this->resolveUsing)($item, $this);
        }

        return data_get($item, self::wildcardPath($path));
    }

    /**
     * Stores one file of a value, and gives its stored value.
     *
     * @return array{disk: string, path: string, name: string, mime: string|null, size: int|null}|null
     */
    private function store(mixed $value, string $path, ?int $index, FileRemovals $removals, SaveContext $context): ?array
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof UploadedFile) {
            return $this->copyUploadedFile($value, $path, $context);
        }
        if (is_array($value) && array_key_exists('upload', $value)) {
            $upload = $context->upload($value);
            if ($upload === null) {
                throw ValidationException::withMessages([$path => __('The upload of the :attribute does not exist.', ['attribute' => $this->attributeName()])]);
            }

            return $this->copyUpload($upload, $value, $path, $context);
        }
        if (is_array($value)) {
            $file = self::findStored($value, $index, $removals->candidates, $this->url(...));
            if ($file !== null) {
                $removals->keep($file);

                return $file;
            }
        }

        throw ValidationException::withMessages([$path => __('The :attribute is not a file of this item.', ['attribute' => $this->attributeName()])]);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array{disk: string, path: string, name: string, mime: string|null, size: int|null}
     */
    private function copyUpload(Upload $upload, array $value, string $path, SaveContext $context): array
    {
        $stream = $upload->readStream();
        if (! is_resource($stream)) {
            throw ValidationException::withMessages([$path => __('The file of the :attribute is missing.', ['attribute' => $this->attributeName()])]);
        }
        // The name the client sends only names a file whose upload has none.
        $name = $upload->name ?? (is_string($value['name'] ?? null) ? $value['name'] : $upload->uid);
        $file = $this->newFile($name, $upload->mime, $upload->size);
        try {
            $written = Storage::disk($file['disk'])->writeStream($file['path'], $stream, ['visibility' => $this->visibility]);
        } finally {
            self::close($stream);
        }
        $this->written($file, $written, $context);

        return $file;
    }

    /**
     * @return array{disk: string, path: string, name: string, mime: string|null, size: int|null}
     */
    private function copyUploadedFile(UploadedFile $uploaded, string $path, SaveContext $context): array
    {
        $file = $this->newFile(
            $uploaded->getClientOriginalName(),
            $uploaded->getMimeType() ?? $uploaded->getClientMimeType(),
            ($size = $uploaded->getSize()) !== false ? $size : null,
        );
        $written = Storage::disk($file['disk'])->putFileAs(
            dirname($file['path']),
            $uploaded,
            basename($file['path']),
            ['visibility' => $this->visibility],
        );
        $this->written($file, $written !== false, $context);

        return $file;
    }

    /**
     * The stored value of a new file: a path of its own, with a safe version of its name.
     *
     * @return array{disk: string, path: string, name: string, mime: string|null, size: int|null}
     */
    private function newFile(string $name, ?string $mime, ?int $size): array
    {
        $name = self::displayName($name);
        $directory = ($this->directory !== '' ? $this->directory.'/' : '').Str::lower((string) Str::ulid());

        return [
            'disk' => $this->getDisk(),
            'path' => $directory.'/'.self::safeName($name),
            'name' => $name,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /**
     * Deletes a new file if the save fails.
     *
     * @param  array{disk: string, path: string}  $file
     */
    private function written(array $file, bool $written, SaveContext $context): void
    {
        if (! $written) {
            throw new RuntimeException(sprintf('Could not write the file "%s" on the disk "%s".', $file['path'], $file['disk']));
        }
        $context->afterFailure(function () use ($file): void {
            Storage::disk($file['disk'])->delete($file['path']);
        });
    }

    /**
     * What the save removes: the stored files at this path, in every block of a list. Shared by
     * the values of the field in every block, so a file kept in any block is kept.
     */
    private function removals(string $path, SaveContext $context): FileRemovals
    {
        $wildcard = self::wildcardPath($path);

        return $context->shared('panneau.file:'.$wildcard, function () use ($context, $path): FileRemovals {
            $removals = new FileRemovals(self::storedFiles($this->currentValue($context->item, $path)));
            $context->afterSave(fn () => $removals->delete());

            return $removals;
        });
    }

    /**
     * Checks one value: an upload of the user, a file in the request, or a stored file of the
     * item.
     *
     * @param  Closure(): list<array{disk: string, path: string, name: string, mime: string|null, size: int|null}>  $candidates
     * @return Closure(string, mixed, Closure): void
     */
    private function fileRule(ValidationContext $context, Closure $candidates): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context, $candidates): void {
            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail(__('The :attribute failed to upload.'));
                }

                return;
            }
            if (is_array($value) && array_key_exists('upload', $value)) {
                if ($context->uploads === null || ($context->uploads)($value) === null) {
                    $fail(__('The upload of the :attribute does not exist.'));
                }

                return;
            }
            if (is_array($value) && is_string($value['name'] ?? null)) {
                $index = $this->multiple && is_numeric($last = Str::afterLast($attribute, '.')) ? (int) $last : null;
                if (self::findStored($value, $index, $candidates(), $this->url(...)) === null) {
                    $fail(__('The :attribute is not a file of this item.'));
                }

                return;
            }
            $fail(__('The :attribute must be a file.'));
        };
    }

    /**
     * The `accept` and `maxSize` rules, checked on the file received. Stored files sent back
     * were checked when they were received.
     *
     * @return list<Closure(string, mixed, Closure): void>
     */
    private function receivedFileRules(ValidationContext $context): array
    {
        $checks = [];
        foreach ($this->portableRules as $rule) {
            $value = $rule['value'] ?? null;
            $message = isset($rule['message']) && is_string($rule['message']) ? $rule['message'] : null;
            if ($rule['rule'] === 'accept' && is_array($value)) {
                $types = array_values(array_filter($value, is_string(...)));
                $checks[] = function (string $attribute, mixed $file, Closure $fail) use ($context, $types, $message): void {
                    $received = self::received($file, $context);
                    if ($received !== null && ($received['mime'] === null || ! self::matchesMime($received['mime'], $types))) {
                        $fail($message ?? __('The :attribute must be a file of type: :types.', ['types' => implode(', ', $types)]));
                    }
                };
            }
            if ($rule['rule'] === 'maxSize' && is_int($value)) {
                $checks[] = function (string $attribute, mixed $file, Closure $fail) use ($context, $value, $message): void {
                    $received = self::received($file, $context);
                    if ($received !== null && ($received['size'] === null || $received['size'] > $value)) {
                        $fail($message ?? __('The :attribute must not be larger than :size bytes.', ['size' => $value]));
                    }
                };
            }
        }

        return $checks;
    }

    /**
     * The MIME type and size of a file received: an upload, as recorded when it completed, or a
     * file in the request, as detected. Null for anything else.
     *
     * @return array{mime: string|null, size: int|null}|null
     */
    private static function received(mixed $value, ValidationContext $context): ?array
    {
        if ($value instanceof UploadedFile) {
            // A file that failed to upload is reported by the file rule.
            if (! $value->isValid()) {
                return null;
            }
            $size = $value->getSize();

            return ['mime' => $value->getMimeType(), 'size' => $size !== false ? $size : null];
        }
        if (is_array($value) && array_key_exists('upload', $value) && $context->uploads !== null) {
            $upload = ($context->uploads)($value);

            return $upload !== null ? ['mime' => $upload->mime, 'size' => $upload->size] : null;
        }

        return null;
    }

    /**
     * The stored file of the item that a stored file sent back stands for: the one with the same
     * URL (without its query string, which changes for temporary URLs), or else the same name,
     * preferring the one at the same position.
     *
     * @param  array<array-key, mixed>  $sent
     * @param  list<array{disk: string, path: string, name: string, mime: string|null, size: int|null}>  $candidates
     * @param  Closure(array{disk: string, path: string}): ?string  $url
     * @return array{disk: string, path: string, name: string, mime: string|null, size: int|null}|null
     */
    private static function findStored(array $sent, ?int $index, array $candidates, Closure $url): ?array
    {
        $sentUrl = is_string($sent['url'] ?? null) && $sent['url'] !== '' ? strtok($sent['url'], '?#') : null;
        if ($sentUrl !== null) {
            foreach ($candidates as $candidate) {
                $candidateUrl = $url($candidate);
                if ($candidateUrl !== null && strtok($candidateUrl, '?#') === $sentUrl) {
                    return $candidate;
                }
            }
        }
        $name = $sent['name'] ?? null;
        if (! is_string($name)) {
            return null;
        }
        if ($index !== null && ($candidates[$index]['name'] ?? null) === $name) {
            return $candidates[$index];
        }
        foreach ($candidates as $candidate) {
            if ($candidate['name'] === $name) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The stored files of a stored value: one file, a list, or the lists of several blocks.
     * A JSON string is decoded.
     *
     * @return list<array{disk: string, path: string, name: string, mime: string|null, size: int|null}>
     */
    public static function storedFiles(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }
        if (! is_array($value)) {
            return [];
        }
        if (is_string($value['disk'] ?? null) && is_string($value['path'] ?? null) && $value['path'] !== '') {
            return [[
                'disk' => $value['disk'],
                'path' => $value['path'],
                'name' => is_string($value['name'] ?? null) && $value['name'] !== '' ? $value['name'] : basename($value['path']),
                'mime' => is_string($value['mime'] ?? null) ? $value['mime'] : null,
                'size' => is_int($value['size'] ?? null) ? $value['size'] : null,
            ]];
        }
        $files = [];
        foreach ($value as $entry) {
            if (is_array($entry) || $entry instanceof Arrayable || $entry instanceof \Traversable) {
                array_push($files, ...self::storedFiles($entry));
            }
        }

        return $files;
    }

    /**
     * A path with the indices of blocks replaced by `*`: `blocks.2.file` gives `blocks.*.file`.
     */
    private static function wildcardPath(string $path): string
    {
        return implode('.', array_map(
            fn (string $segment): string => ctype_digit($segment) ? '*' : $segment,
            explode('.', $path),
        ));
    }

    /**
     * The name of a file as the user knows it, without directories or control characters.
     */
    private static function displayName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        return $name !== '' && $name !== '.' && $name !== '..' ? Str::limit($name, 255, '') : 'file';
    }

    /**
     * A name that is safe in a path on any disk: letters, digits, `.`, `-` and `_`, with its
     * extension.
     */
    private static function safeName(string $name): string
    {
        $extension = Str::lower((string) preg_replace('/[^A-Za-z0-9]+/', '', pathinfo($name, PATHINFO_EXTENSION)));
        $base = Str::slug(Str::ascii(pathinfo($name, PATHINFO_FILENAME)));
        $base = Str::limit($base !== '' ? $base : 'file', 120, '');

        return $extension !== '' ? $base.'.'.Str::limit($extension, 16, '') : $base;
    }

    /**
     * Closes a stream, unless the disk's driver already did.
     */
    private static function close(mixed $stream): void
    {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private function attributeName(): string
    {
        return $this->label ?? str_replace('_', ' ', $this->name);
    }
}
