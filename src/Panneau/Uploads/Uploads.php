<?php

namespace Panneau\Uploads;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Panneau\Contracts\UploadStorage;
use RuntimeException;

/**
 * Direct uploads: the browser sends files to the disk itself, with URLs that Panneau signs,
 * then values reference them as `{ "upload": "upl_…" }`. An upload belongs to the user who
 * started it: for anyone else, it doesn't exist.
 */
final class Uploads
{
    public function __construct(
        private readonly Config $config,
        private readonly UploadStorage $storage,
        private readonly Filesystems $filesystems,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('panneau.uploads.enabled', false);
    }

    public function disk(): string
    {
        $disk = $this->config->get('panneau.uploads.disk');

        return is_string($disk) && $disk !== '' ? $disk : (string) $this->config->get('filesystems.default');
    }

    public function urlTtl(): int
    {
        return max(1, (int) $this->config->get('panneau.uploads.url_ttl', 900));
    }

    public function maxSize(): ?int
    {
        $size = $this->config->get('panneau.uploads.max_size');

        return is_numeric($size) ? (int) $size : null;
    }

    /**
     * Whether values can reference uploads: with direct uploads, or with the tools of the
     * agents, which store the files they are given as uploads (decision 0032).
     */
    public function accepted(): bool
    {
        return $this->enabled() || $this->config->get('panneau.agents.mcp.enabled') === true;
    }

    /**
     * Starts an upload for a user, with its own key: `{path}/{upl_…}`, whatever key the
     * browser proposed.
     */
    public function start(Authenticatable $user, ?string $name = null): Upload
    {
        $uid = 'upl_'.Str::lower((string) Str::ulid());
        $path = trim((string) $this->config->get('panneau.uploads.path', 'panneau-uploads'), '/');

        $upload = new Upload;
        $upload->forceFill([
            'uid' => $uid,
            'user_id' => (string) $user->getAuthIdentifier(),
            'status' => UploadStatus::Open,
            'disk' => $this->disk(),
            'path' => ($path !== '' ? $path.'/' : '').$uid,
            'name' => $name,
            'expires_at' => Carbon::now()->addSeconds(max(1, (int) $this->config->get('panneau.uploads.lifetime', 86400))),
        ])->save();

        return $upload;
    }

    /**
     * Stores a file as a completed upload of a user, on the disk of the uploads: what a client
     * that can't send the file to the disk itself gives, an agent for one. The disk needs no
     * signing. The type is the one detected in the contents, never one the client names: the
     * extension of the name only tells apart contents that are plain text or unknown (a CSV).
     */
    public function store(Authenticatable $user, string $name, string $contents): Upload
    {
        $upload = $this->start($user, $name);
        $mime = (new FinfoMimeTypeDetector)->detectMimeType($name, $contents);
        $written = $this->filesystems->disk($upload->disk)->put($upload->path, $contents, array_filter(['ContentType' => $mime]));
        if ($written === false) {
            $upload->delete();

            throw new RuntimeException(sprintf('The file could not be written on the disk "%s" of the uploads.', $upload->disk));
        }
        $upload->forceFill([
            'status' => UploadStatus::Completed,
            'mime' => $mime,
            'size' => strlen($contents),
        ])->save();

        return $upload;
    }

    /**
     * Completes an upload whose file the client sent to the disk: records the size and type the
     * disk has. A file larger than `panneau.uploads.max_size` is deleted with its upload.
     * Completing twice changes nothing.
     */
    public function complete(Upload $upload): UploadCompletion
    {
        if ($upload->isCompleted()) {
            return UploadCompletion::Completed;
        }
        $stat = $this->storage->stat($upload->disk, $upload->path);
        if ($stat === null) {
            return UploadCompletion::Missing;
        }
        $maxSize = $this->maxSize();
        if ($maxSize !== null && $stat['size'] > $maxSize) {
            $this->storage->delete($upload->disk, $upload->path);
            $upload->delete();

            return UploadCompletion::TooLarge;
        }
        $upload->forceFill([
            'status' => UploadStatus::Completed,
            'size' => $stat['size'],
            'mime' => $stat['mime'],
        ])->save();

        return UploadCompletion::Completed;
    }

    /**
     * The open upload of a user whose key the browser sends, or null for any other key.
     */
    public function findOpenByKey(Authenticatable $user, string $key): ?Upload
    {
        $uid = Str::afterLast($key, '/');
        $upload = $this->find($user, $uid);

        return $upload !== null
            && ! $upload->isCompleted()
            && $this->storage->key($upload->disk, $upload->path) === $key
                ? $upload
                : null;
    }

    /**
     * An upload of a user that hasn't expired, by its id.
     */
    public function find(Authenticatable $user, string $uid): ?Upload
    {
        return Upload::query()
            ->where('uid', $uid)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * The completed upload that a value references (`{ "upload": "upl_…" }`) for a user, or null
     * when it isn't one, or when nothing makes uploads (`accepted()`). Actions and file fields
     * read the file with `readStream()`.
     */
    public function resolve(mixed $value, ?Authenticatable $user): ?Upload
    {
        $uid = is_array($value) ? ($value['upload'] ?? null) : null;
        if (! is_string($uid) || $user === null || ! $this->accepted()) {
            return null;
        }
        $upload = $this->find($user, $uid);

        return $upload?->isCompleted() === true ? $upload : null;
    }

    /**
     * The upload, as the protocol's `Upload`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Upload $upload): array
    {
        return array_filter([
            'id' => $upload->uid,
            'key' => $this->storage->key($upload->disk, $upload->path),
            'name' => $upload->name,
            'mime' => $upload->mime,
            'size' => $upload->size,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Removes the uploads that expired, used or not, with their files. Returns how many.
     */
    public function prune(): int
    {
        $count = 0;
        Upload::query()
            ->where('expires_at', '<=', Carbon::now())
            ->each(function (Upload $upload) use (&$count): void {
                $this->storage->delete(
                    $upload->disk,
                    $upload->path,
                    $upload->isCompleted() ? null : $upload->provider_upload_id,
                );
                $upload->delete();
                $count++;
            });

        return $count;
    }
}
