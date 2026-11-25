<?php

namespace Panneau\Agents\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Panneau\Agents\FileUpload;
use Panneau\Agents\ToolCall;
use Panneau\Auth\Authorizer;
use Panneau\Resources\MediaResource;
use Panneau\Resources\ResourceRegistry;

/**
 * `upload_file`: lets an agent add a file to a media library (decision 0032). The file never
 * goes through the model: the tool answers a temporary signed URL of Panneau, the agent sends
 * the file there with one `PUT`, and the answer of that request is the new media, whose `id` is
 * the value of `media` fields in `save_item`.
 *
 * The tool writes nothing itself, and dispatches no `AgentToolCalled`: the route that receives
 * the file creates the media, and dispatches it.
 */
final class UploadFile extends BaseTool
{
    public const NAME = 'upload_file';

    public function __construct(
        ValidatorFactory $validator,
        Config $config,
        private readonly FileUpload $uploads,
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
    ) {
        parent::__construct($validator, $config);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'Adds a file to a media library, a resource of kind `media`: an image, a video, a document. '
            .'This tool doesn\'t take the file: it answers a `url`. Send the file there yourself, as the body of one HTTP `PUT`, '
            .'with nothing else (`curl -T cover.png "$url"`): the URL needs no token, and is valid for `expiresIn` seconds. '
            .'The answer of that `PUT` is the new media, as `get_item` gives it: its `id` is the value of a media field in `save_item`. '
            .'Each `PUT` makes a media: send the file once. '
            .'`values` sets the other fields of the media, a title or an alternative text: `describe` tells the fields of the library.';
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
                'resource' => ['type' => 'string', 'minLength' => 1, 'description' => 'The id of the media library. Not needed when you can add files to one library only.'],
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'description' => 'The name of the file, with its extension: `cover.png`.'],
                'values' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'description' => 'The other fields of the media, by field, as in `save_item`. Not its `file`: it is the body of the `PUT`.',
                ],
            ],
            'required' => ['name'],
            'additionalProperties' => false,
        ];
    }

    protected function rules(): array
    {
        return [
            'resource' => ['sometimes', 'string', 'min:1'],
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'values' => ['sometimes', 'array'],
        ];
    }

    protected function conflicts(array $arguments): array
    {
        $values = $arguments['values'] ?? null;

        return is_array($values) && array_key_exists(MediaResource::FILE, $values)
            ? ['values' => __('The file is the body of the `PUT`, not a value.')]
            : [];
    }

    protected function run(ToolCall $call, array $arguments): array
    {
        $user = $call->user;
        if ($user === null) {
            throw new AuthorizationException;
        }
        $resource = $this->library($user, $arguments['resource'] ?? null);
        /** @var array<string, mixed> $values */
        $values = $arguments['values'] ?? [];
        $url = $this->uploads->url($user, $call->agent, $resource, $arguments['name'], $values);
        if (strlen($url) > FileUpload::MAX_URL_LENGTH) {
            throw ValidationException::withMessages(['values' => [
                __('These values are too long for an upload: leave them out, and set them with `save_item` on the media you get.'),
            ]]);
        }

        return [
            'resource' => $resource->id(),
            'name' => $arguments['name'],
            'method' => 'PUT',
            'url' => $url,
            'expiresIn' => $this->uploads->ttl(),
        ];
    }

    /**
     * Signing a URL writes nothing.
     */
    protected function wrote(array $arguments, array $data): bool
    {
        return false;
    }

    /**
     * The library of an upload: the one named, which the user must be able to create in, or the
     * only library they can create in when none is named.
     *
     * @throws ValidationException When the resource isn't a library, or when none is named and
     *                             the user can create in several, or in none.
     * @throws AuthorizationException When the user can't create in the library named.
     */
    private function library(Authenticatable $user, ?string $id): MediaResource
    {
        $creatable = array_values(array_filter(
            $this->resources->libraries(),
            fn (MediaResource $library): bool => $this->authorizer->allowsResource($user, $library, 'create'),
        ));
        $ids = implode(', ', array_map(fn (MediaResource $library): string => '`'.$library->id().'`', $creatable));
        if ($id !== null) {
            $resource = $this->resources->find($id);
            if (! $resource instanceof MediaResource) {
                throw ValidationException::withMessages(['resource' => [$creatable === []
                    ? __('`:resource` is not a media library.', ['resource' => $id])
                    : __('`:resource` is not a media library. The libraries you can add files to: :libraries.', ['resource' => $id, 'libraries' => $ids]),
                ]]);
            }
            if (! in_array($resource, $creatable, true)) {
                throw new AuthorizationException;
            }

            return $resource;
        }
        if (count($creatable) === 1) {
            return $creatable[0];
        }

        throw ValidationException::withMessages(['resource' => [$creatable === []
            ? __('There is no media library you can add files to.')
            : __('Name the media library, one of: :libraries.', ['libraries' => $ids]),
        ]]);
    }
}
