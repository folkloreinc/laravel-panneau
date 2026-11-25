<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\UploadStorage;
use Panneau\Http\Controllers\Concerns\ReadsRouteParameters;
use Panneau\Http\Problem;
use Panneau\Uploads\S3Operation;
use Panneau\Uploads\Uploads;
use Panneau\Uploads\UploadStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Direct uploads, for Uppy 6's `@uppy/aws-s3`: the browser sends every S3 request to the disk
 * itself, with a URL this controller presigns, then completes the upload here (decision 0025 of
 * panneau-js).
 *
 * - The request that starts an upload gets its own key, `{path}/{upl_…}`, whatever key the
 *   browser proposed. Any other request is signed only for the key of an open upload of the
 *   same user, and only for the multipart upload it started: the first `uploadId` signed for an
 *   upload is the only one it accepts.
 * - Completing an upload checks that the file is on the disk, and its size. It doesn't use the
 *   file: values reference the upload.
 *
 * @internal
 */
final class UploadController
{
    use ReadsRouteParameters;

    private const MAX_PARTS = 10000;

    public function __construct(
        private readonly Uploads $uploads,
        private readonly UploadStorage $storage,
        private readonly Authorizer $authorizer,
        private readonly ValidationFactory $validator,
    ) {}

    public function sign(Request $request): JsonResponse
    {
        $user = $this->authorize($request);
        $input = $this->validator->make($request->all(), [
            'method' => ['required', 'string', 'in:GET,PUT,POST,DELETE'],
            'key' => ['required', 'string', 'max:1024'],
            'uploadId' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'partNumber' => ['sometimes', 'nullable', 'integer', 'between:1,'.self::MAX_PARTS],
            'contentType' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $uploadId = is_string($input['uploadId'] ?? null) && $input['uploadId'] !== '' ? $input['uploadId'] : null;
        $partNumber = is_numeric($input['partNumber'] ?? null) ? (int) $input['partNumber'] : null;
        $operation = S3Operation::fromRequest((string) $input['method'], $uploadId !== null, $partNumber !== null);
        if ($operation === null) {
            return Problem::response(422, 'The request is invalid.', Problem::VALIDATION, extra: [
                'errors' => ['method' => ['No S3 request has this method, upload id and part number.']],
            ]);
        }

        if ($operation->startsUpload()) {
            $upload = $this->uploads->start($user);

            return new JsonResponse([
                'url' => $this->storage->sign($upload->disk, $upload->path, $operation, $this->uploads->urlTtl()),
                'key' => $this->storage->key($upload->disk, $upload->path),
                'upload' => $upload->uid,
            ]);
        }

        $upload = $this->uploads->findOpenByKey($user, (string) $input['key']);
        if ($upload === null) {
            throw new NotFoundHttpException('No open upload has this key.');
        }
        if ($uploadId !== null) {
            // The first multipart upload signed for a key is the only one it accepts.
            if ($upload->provider_upload_id === null) {
                $upload->forceFill(['provider_upload_id' => $uploadId])->save();
            } elseif ($upload->provider_upload_id !== $uploadId) {
                throw new NotFoundHttpException('No open upload has this key and upload id.');
            }
        }

        return new JsonResponse([
            'url' => $this->storage->sign($upload->disk, $upload->path, $operation, $this->uploads->urlTtl(), $uploadId, $partNumber),
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $this->authorize($request);
        $input = $this->validator->make($request->all(), [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $found = $this->uploads->find($user, $this->routeParameter($request, 'upload'));
        if ($found === null) {
            throw new NotFoundHttpException('Upload not found.');
        }

        if (! $found->isCompleted()) {
            $stat = $this->storage->stat($found->disk, $found->path);
            if ($stat === null) {
                return Problem::response(422, 'The file is not on the storage yet.', Problem::VALIDATION, extra: [
                    'errors' => ['upload' => ['Complete the upload on the storage first.']],
                ]);
            }
            $maxSize = $this->uploads->maxSize();
            if ($maxSize !== null && $stat['size'] > $maxSize) {
                $this->storage->delete($found->disk, $found->path);
                $found->delete();

                return Problem::response(422, 'The file is too large.', Problem::VALIDATION, extra: [
                    'errors' => ['upload' => [sprintf('Files can\'t be larger than %d bytes.', $maxSize)]],
                ]);
            }
            $found->forceFill([
                'status' => UploadStatus::Completed,
                'size' => $stat['size'],
                'mime' => $stat['mime'],
            ]);
        }
        if (is_string($input['name'] ?? null) && $input['name'] !== '') {
            $found->forceFill(['name' => $input['name']]);
        }
        $found->save();

        return new JsonResponse(['data' => $this->uploads->toArray($found)]);
    }

    private function authorize(Request $request): Authenticatable
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable || ! $this->authorizer->allowsUploads($user)) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
