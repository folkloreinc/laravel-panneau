<?php

namespace Panneau\Uploads;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Panneau\Contracts\UploadStorage;

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
     * Starts an upload for a user, with its own key: `{path}/{upl_…}`, whatever key the
     * browser proposed.
     */
    public function start(Authenticatable $user): Upload
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
            'expires_at' => Carbon::now()->addSeconds(max(1, (int) $this->config->get('panneau.uploads.lifetime', 86400))),
        ])->save();

        return $upload;
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
     * when it isn't one. Actions and repositories read the file with `readStream()`.
     */
    public function resolve(mixed $value, ?Authenticatable $user): ?Upload
    {
        $uid = is_array($value) ? ($value['upload'] ?? null) : null;
        if (! is_string($uid) || $user === null) {
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
