<?php

namespace Panneau\Uploads;

use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Filesystem\AwsS3V3Adapter;
use InvalidArgumentException;
use Panneau\Contracts\UploadStorage;
use RuntimeException;
use Throwable;

/**
 * Direct uploads on an S3-compatible disk, through the disk's own client. Signing needs no
 * request to the storage. Needs `league/flysystem-aws-s3-v3`.
 */
final class S3UploadStorage implements UploadStorage
{
    public function __construct(
        private readonly Filesystems $filesystems,
    ) {}

    public function sign(string $disk, string $path, S3Operation $operation, int $ttl, ?string $uploadId = null, ?int $partNumber = null): string
    {
        [$client, $bucket, $key] = $this->target($disk, $path);

        if ($operation->needsUploadId() && ($uploadId === null || $uploadId === '')) {
            throw new InvalidArgumentException(sprintf('%s needs the id of a multipart upload.', $operation->value));
        }
        if ($operation === S3Operation::UploadPart && $partNumber === null) {
            throw new InvalidArgumentException('UploadPart needs a part number.');
        }

        $parameters = array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'UploadId' => $operation->needsUploadId() ? $uploadId : null,
            'PartNumber' => $operation === S3Operation::UploadPart ? $partNumber : null,
        ], fn (mixed $value): bool => $value !== null);

        // Relative to the SDK's clock, which computes X-Amz-Expires.
        return (string) $client->createPresignedRequest($client->getCommand($operation->value, $parameters), '+'.$ttl.' seconds')->getUri();
    }

    public function key(string $disk, string $path): string
    {
        return $this->target($disk, $path)[2];
    }

    public function stat(string $disk, string $path): ?array
    {
        $filesystem = $this->filesystems->disk($disk);
        if (! $filesystem->exists($path)) {
            return null;
        }

        try {
            $mime = $filesystem->mimeType($path);
        } catch (Throwable) {
            $mime = false;
        }

        return ['size' => $filesystem->size($path), 'mime' => is_string($mime) ? $mime : null];
    }

    public function delete(string $disk, string $path, ?string $uploadId = null): void
    {
        if ($uploadId !== null && $uploadId !== '') {
            [$client, $bucket, $key] = $this->target($disk, $path);
            try {
                $client->abortMultipartUpload(['Bucket' => $bucket, 'Key' => $key, 'UploadId' => $uploadId]);
            } catch (Throwable) {
                // Completed or already aborted: there is nothing to abort.
            }
        }
        $this->filesystems->disk($disk)->delete($path);
    }

    /**
     * @return array{S3Client, string, string}
     */
    private function target(string $disk, string $path): array
    {
        $filesystem = $this->filesystems->disk($disk);
        if (! $filesystem instanceof AwsS3V3Adapter) {
            throw new RuntimeException(sprintf('The disk "%s" of panneau.uploads.disk is not an S3-compatible disk, which direct uploads need.', $disk));
        }
        $bucket = $filesystem->getConfig()['bucket'] ?? null;
        if (! is_string($bucket) || $bucket === '') {
            throw new RuntimeException(sprintf('The disk "%s" of panneau.uploads.disk has no bucket.', $disk));
        }

        return [$filesystem->getClient(), $bucket, $filesystem->path($path)];
    }
}
