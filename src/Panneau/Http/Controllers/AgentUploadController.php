<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Panneau\Agents\AgentCaller;
use Panneau\Agents\FileUpload;
use Panneau\Agents\Tools\UploadFile;
use Panneau\Auth\Authorizer;
use Panneau\Events\AgentToolCalled;
use Panneau\Http\Middleware\AuthorizeAgentUpload;
use Panneau\Operations\Concerns\SerializesItems;
use Panneau\Operations\CreateItem;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\MediaResource;
use Panneau\Resources\ResourceRegistry;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Receives the file an agent sends to the URL of `upload_file` (decision 0032): the body of the
 * `PUT` is the file. It becomes the `file` of a new media of the library the URL was made for,
 * created by `CreateItem` as the user, like a file added in the admin: the policy, `accept`,
 * `maxSize`, the scope and the media types of the library all apply. The answer is the media as
 * `get_item` gives it, with a `201`, or a problem.
 *
 * @internal
 */
final class AgentUploadController
{
    use SerializesItems;

    public function __construct(
        private readonly FileUpload $uploads,
        private readonly CreateItem $createItem,
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{user: string, agent: AgentCaller|null, context: array<string, string>, resource: string, name: string, values: array<string, mixed>} $claims */
        $claims = $request->attributes->get(AuthorizeAgentUpload::ATTRIBUTE);
        $user = $request->user();
        $resource = $this->resources->find($claims['resource']);
        if (! $resource instanceof MediaResource) {
            throw new NotFoundHttpException;
        }

        $path = $this->receive($request);
        try {
            $saved = $this->createItem->handle(
                $user,
                $resource,
                // The file of the request, whatever the values name
                [...$claims['values'], MediaResource::FILE => new UploadedFile($path, $claims['name'], null, UPLOAD_ERR_OK, true)],
                $request,
                $claims['agent'],
            );
        } finally {
            @unlink($path);
        }
        $item = $this->serializeItem($user, $resource, $saved['item'], $saved['references']);

        // The media is saved: a listener that throws is reported, and never fails the upload
        try {
            $id = $item['id'] ?? null;
            event(new AgentToolCalled($user, $claims['agent'], UploadFile::NAME, $resource->id(), is_int($id) || is_string($id) ? [(string) $id] : []));
        } catch (Throwable $exception) {
            report($exception);
        }

        return new JsonResponse($item, 201);
    }

    /**
     * Copies the body of the request to a temporary file, as a stream: the file is never in
     * memory. A body over `panneau.agents.uploads.max_size` is a `413`, read no further than
     * the limit.
     */
    private function receive(Request $request): string
    {
        $maxSize = $this->uploads->maxSize();
        $tooLarge = fn (): HttpException => new HttpException(413, __('Files can\'t be larger than :size bytes.', ['size' => (int) $maxSize]));
        $length = $request->headers->get('Content-Length');
        if ($maxSize !== null && is_numeric($length) && (int) $length > $maxSize) {
            throw $tooLarge();
        }
        $path = tempnam(sys_get_temp_dir(), 'panneau-upload-');
        $target = $path !== false ? fopen($path, 'wb') : false;
        if ($path === false || $target === false) {
            throw new RuntimeException('The file of an agent could not be written to a temporary file.');
        }
        $source = $request->getContent(true);
        try {
            // One byte over the limit tells a body that is too large
            $copied = stream_copy_to_stream($source, $target, $maxSize !== null ? $maxSize + 1 : null);
        } finally {
            fclose($target);
            fclose($source);
        }
        if ($copied === false || ($maxSize !== null && $copied > $maxSize)) {
            @unlink($path);

            throw $copied === false ? new RuntimeException('The file of an agent could not be read.') : $tooLarge();
        }
        if ($copied === 0) {
            @unlink($path);

            throw new HttpException(400, __('The body of the request is the file: it is empty.'));
        }

        return $path;
    }
}
