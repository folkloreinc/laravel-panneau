<?php

namespace Panneau\Contracts;

use Panneau\Uploads\S3Operation;

/**
 * The storage of direct uploads: it presigns the requests the browser sends to the disk, and
 * reads what the disk received. The default one uses the client of an S3-compatible disk.
 */
interface UploadStorage
{
    /**
     * A presigned URL for one request the browser sends to the disk, with the operation's HTTP
     * method.
     */
    public function sign(string $disk, string $path, S3Operation $operation, int $ttl, ?string $uploadId = null, ?int $partNumber = null): string;

    /**
     * The key of a file in the disk's bucket, with the disk's root: what the browser uses.
     */
    public function key(string $disk, string $path): string;

    /**
     * The size, in bytes, and the type of the file, or null when the disk doesn't have it.
     *
     * @return array{size: int, mime: string|null}|null
     */
    public function stat(string $disk, string $path): ?array;

    /**
     * Removes the file, and aborts the multipart upload that hasn't completed.
     */
    public function delete(string $disk, string $path, ?string $uploadId = null): void;
}
