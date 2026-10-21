<?php

namespace Panneau\Uploads;

/**
 * An S3 request the browser sends to the storage itself, with a URL that Panneau presigns
 * (Uppy 6's `signRequest`).
 */
enum S3Operation: string
{
    case CreateMultipartUpload = 'CreateMultipartUpload';
    case PutObject = 'PutObject';
    case UploadPart = 'UploadPart';
    case ListParts = 'ListParts';
    case CompleteMultipartUpload = 'CompleteMultipartUpload';
    case AbortMultipartUpload = 'AbortMultipartUpload';
    case DeleteObject = 'DeleteObject';

    /**
     * The operation of a request, from its HTTP method and whether it names a multipart upload
     * and a part, as Uppy describes it. Null when no operation matches.
     */
    public static function fromRequest(string $method, bool $hasUploadId, bool $hasPartNumber = false): ?self
    {
        return match ([strtoupper($method), $hasUploadId, $hasPartNumber]) {
            ['POST', false, false] => self::CreateMultipartUpload,
            ['PUT', false, false] => self::PutObject,
            ['PUT', true, true] => self::UploadPart,
            ['GET', true, false] => self::ListParts,
            ['POST', true, false] => self::CompleteMultipartUpload,
            ['DELETE', true, false] => self::AbortMultipartUpload,
            ['DELETE', false, false] => self::DeleteObject,
            default => null,
        };
    }

    /**
     * Whether the request starts an upload, which gets its own key.
     */
    public function startsUpload(): bool
    {
        return $this === self::CreateMultipartUpload || $this === self::PutObject;
    }

    /**
     * Whether the request names a multipart upload by its id.
     */
    public function needsUploadId(): bool
    {
        return ! $this->startsUpload() && $this !== self::DeleteObject;
    }
}
