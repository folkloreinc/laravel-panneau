<?php

namespace Panneau\Agents\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Panneau\Agents\ToolCall;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\UploadStorage;
use Panneau\Uploads\S3Operation;
use Panneau\Uploads\Upload;
use Panneau\Uploads\UploadCompletion;
use Panneau\Uploads\Uploads;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `upload_file`: gives an agent an upload, the value of a file field (decision 0032). The file
 * comes one of two ways, both on the direct uploads of decision 0007:
 *
 * - **in `content`,** in base64, for a small file: stored as a completed upload;
 * - **by a presigned URL,** for the others: the tool starts an upload and signs one `PUT`, the
 *   agent sends the file there, then calls the tool with the `upload` to complete it. Only with
 *   `panneau.uploads.enabled`, whose disk signs.
 *
 * The upload is the user's, authorized by the `uploadPanneauFiles` gate, and temporary:
 * `panneau:prune-uploads` removes it like the others. `AgentToolCalled` is dispatched by the
 * calls that answer a completed upload, not by the one that only signs a URL.
 */
final class UploadFile extends BaseTool
{
    private const STATUS_COMPLETED = 'completed';

    private const STATUS_OPEN = 'open';

    public function __construct(
        ValidatorFactory $validator,
        Config $config,
        private readonly Uploads $uploads,
        private readonly UploadStorage $storage,
        private readonly Authorizer $authorizer,
    ) {
        parent::__construct($validator, $config);
    }

    public function name(): string
    {
        return 'upload_file';
    }

    public function description(): string
    {
        $limit = $this->maxContentSize();
        $description = 'Uploads a file, to set a file field with `save_item`: the value of the field is the `value` this tool answers, `{ "upload": "upl_…" }`. '
            .sprintf('For a file up to %d bytes, send its `name` and its `content` in base64: the upload is completed. ', $limit);
        $description .= $this->uploads->enabled()
            ? 'For a larger one, send its `name` only: you get a `url` to send the file to with one HTTP `PUT`, with the `headers` you get '
                .'(`curl -T cover.png -H "Content-Type: image/png" "$url"`), then call this tool again with the `upload` alone, the `id` you got, to complete it. '
            : 'This site takes no larger file from an agent. ';

        return $description.'An upload is temporary, and yours only: use it in `save_item` soon after.';
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'description' => 'The name of the file, with its extension: `cover.png`. Required to start an upload.'],
                'content' => ['type' => 'string', 'minLength' => 1, 'description' => 'The contents of the file in base64, or as a `data:` URL in base64. Without it, the tool answers a `url` to send the file to.'],
                'upload' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of an upload whose file was sent to its `url`, to complete it. Alone.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required_without:upload', 'string', 'min:1', 'max:255'],
            'content' => ['sometimes', 'string', 'min:1'],
            'upload' => ['sometimes', 'string', 'min:1'],
        ];
    }

    protected function conflicts(array $arguments): array
    {
        $conflicts = [];
        if (array_key_exists('upload', $arguments)) {
            foreach (['name', 'content'] as $name) {
                if (array_key_exists($name, $arguments)) {
                    $conflicts[$name] = __('`:name` starts an upload: completing one takes its `upload` alone.', ['name' => $name]);
                }
            }
        }

        return $conflicts;
    }

    protected function run(ToolCall $call, array $arguments): array
    {
        $user = $call->user;
        if ($user === null || ! $this->authorizer->allowsUploads($user)) {
            throw new AuthorizationException;
        }

        if (isset($arguments['upload'])) {
            return $this->complete($user, $arguments['upload']);
        }
        if (isset($arguments['content'])) {
            return $this->completed($this->uploads->store($user, $arguments['name'], $this->decode($arguments['content'])));
        }

        return $this->start($user, $arguments['name']);
    }

    protected function wrote(array $arguments, array $data): bool
    {
        return ($data['status'] ?? null) === self::STATUS_COMPLETED;
    }

    /**
     * Starts an upload and signs the one `PUT` that sends its file.
     *
     * @return array<string, mixed>
     */
    private function start(Authenticatable $user, string $name): array
    {
        if (! $this->uploads->enabled()) {
            throw ValidationException::withMessages(['content' => [
                __('This site takes a file as `content` only, in base64, up to :size bytes.', ['size' => $this->maxContentSize()]),
            ]]);
        }
        $upload = $this->uploads->start($user, $name);
        $ttl = $this->uploads->urlTtl();
        // The disk records the type the `PUT` names, which completing reads: without the header,
        // the file would have none that a field accepts
        $mime = (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($name);

        return [
            'id' => $upload->uid,
            'status' => self::STATUS_OPEN,
            'name' => $name,
            'method' => 'PUT',
            'url' => $this->storage->sign($upload->disk, $upload->path, S3Operation::PutObject, $ttl),
            'headers' => ['Content-Type' => $mime ?? 'application/octet-stream'],
            'expiresIn' => $ttl,
        ];
    }

    /**
     * Completes the upload whose file the agent sent to its URL.
     *
     * @return array<string, mixed>
     */
    private function complete(Authenticatable $user, string $uid): array
    {
        $upload = $this->uploads->find($user, $uid) ?? throw new NotFoundHttpException('Upload not found.');

        return match ($this->uploads->complete($upload)) {
            UploadCompletion::Completed => $this->completed($upload),
            UploadCompletion::Missing => throw ValidationException::withMessages(['upload' => [
                __('The file is not on the storage yet: send it to the `url` of the upload first.'),
            ]]),
            UploadCompletion::TooLarge => throw ValidationException::withMessages(['upload' => [
                __('Files can\'t be larger than :size bytes: the upload was removed.', ['size' => (int) $this->uploads->maxSize()]),
            ]]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function completed(Upload $upload): array
    {
        return array_filter([
            'id' => $upload->uid,
            'status' => self::STATUS_COMPLETED,
            'name' => $upload->name,
            'mime' => $upload->mime,
            'size' => $upload->size,
            'value' => ['upload' => $upload->uid],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The contents of `content`: base64, with or without the header of a `data:` URL, under the
     * limit of the tool.
     */
    private function decode(string $content): string
    {
        if (str_starts_with($content, 'data:')) {
            $content = preg_match('/^data:[^,]*;base64,/', $content, $header) === 1 ? substr($content, strlen($header[0])) : '';
        }
        $limit = $this->maxContentSize();
        // Base64 is four characters for three bytes, with line breaks at most: a file that is
        // clearly over the limit isn't decoded
        if (strlen($content) > (int) (intdiv($limit + 2, 3) * 4 * 1.05) + 16) {
            throw $this->tooLarge($limit);
        }
        $contents = base64_decode((string) preg_replace('/\s+/', '', $content), true);
        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages(['content' => [__('`content` is not a file in base64.')]]);
        }
        if (strlen($contents) > $limit) {
            throw $this->tooLarge($limit);
        }

        return $contents;
    }

    private function tooLarge(int $limit): ValidationException
    {
        return ValidationException::withMessages(['content' => [
            $this->uploads->enabled()
                ? __('A file in `content` can\'t be larger than :size bytes: send its `name` only, then the file to the `url` you get.', ['size' => $limit])
                : __('A file in `content` can\'t be larger than :size bytes.', ['size' => $limit]),
        ]]);
    }

    /**
     * The largest file `content` takes, in bytes: `panneau.agents.uploads.max_content_size`,
     * never over `panneau.uploads.max_size`.
     */
    private function maxContentSize(): int
    {
        $size = $this->config->get('panneau.agents.uploads.max_content_size');
        $limit = is_numeric($size) ? max(0, (int) $size) : 5 * 1024 * 1024;
        $maxSize = $this->uploads->maxSize();

        return $maxSize !== null ? min($limit, $maxSize) : $limit;
    }
}
