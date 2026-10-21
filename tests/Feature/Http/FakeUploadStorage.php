<?php

namespace Panneau\Tests\Feature\Http;

use Panneau\Contracts\UploadStorage;
use Panneau\Uploads\S3Operation;
use Panneau\Uploads\S3UploadStorage;

/**
 * Signs with the real S3 client, which needs no request to the storage, and keeps the files that
 * the browser "sent" in memory.
 */
final class FakeUploadStorage implements UploadStorage
{
    /**
     * @var array<string, array{size: int, mime: string|null}>
     */
    public array $files = [];

    /**
     * @var list<array{path: string, uploadId: string|null}>
     */
    public array $deleted = [];

    public function __construct(private readonly S3UploadStorage $s3) {}

    public function sign(string $disk, string $path, S3Operation $operation, int $ttl, ?string $uploadId = null, ?int $partNumber = null): string
    {
        return $this->s3->sign($disk, $path, $operation, $ttl, $uploadId, $partNumber);
    }

    public function key(string $disk, string $path): string
    {
        return $this->s3->key($disk, $path);
    }

    public function stat(string $disk, string $path): ?array
    {
        return $this->files[$path] ?? null;
    }

    public function delete(string $disk, string $path, ?string $uploadId = null): void
    {
        unset($this->files[$path]);
        $this->deleted[] = ['path' => $path, 'uploadId' => $uploadId];
    }
}
