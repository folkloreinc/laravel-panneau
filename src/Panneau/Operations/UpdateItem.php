<?php

namespace Panneau\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Agents\AgentCaller;
use Panneau\Auth\Authorizer;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Exceptions\VersionConflictException;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Forms\SaveContext;
use Panneau\Media\MediaReferences;
use Panneau\Operations\Concerns\ResolvesItems;
use Panneau\Operations\Concerns\SerializesItems;
use Panneau\Operations\Concerns\ValidatesValues;
use Panneau\Resources\FieldChecks;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Updates an item, as `PATCH /resources/{resource}/{id}` does: the item is found within the
 * resource's scope, the user must be able to update it (`update`), and its version must match
 * `If-Match`. Only the values given, of the fields the user can edit, are validated and saved.
 */
final class UpdateItem
{
    use ResolvesItems;
    use SerializesItems;
    use ValidatesValues;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
        private readonly DefinitionBuilder $definition,
        private readonly ValidationFactory $validator,
        private readonly Uploads $uploads,
        private readonly FieldChecks $checks,
    ) {}

    /**
     * @param  array<string, mixed>  $values  The values to change, as in the body of the request.
     * @param  string|null  $ifMatch  The versions the update expects, as an `If-Match` header.
     *                                Null (or empty) always matches.
     * @param  Request  $request  For the scope of the resource, the media of the values and the
     *                            hooks of the resource.
     * @param  AgentCaller|null  $agent  The agent that saves the item through a tool, given to
     *                                   the fields in the `SaveContext`.
     * @return array{item: object, references: MediaReferences} The updated item, and the media
     *                                                          found while validating it, to serialize it.
     *
     * @throws NotFoundHttpException When the resource or the item doesn't exist, or the item
     *                               is out of the scope.
     * @throws AuthorizationException When the user can't update the item.
     * @throws VersionConflictException When the item's version doesn't match `$ifMatch`.
     * @throws ValidationException When the values aren't valid.
     */
    public function handle(?Authenticatable $user, Resource|string $resource, string $id, array $values, ?string $ifMatch, Request $request, ?AgentCaller $agent = null): array
    {
        $resource = $this->findResource($resource);
        $repository = $this->resources->repository($resource);
        $item = $this->findItem($resource, $id, $request);
        if (! $this->authorizer->allowsItem($user, $resource, 'update', $item)) {
            throw new AuthorizationException;
        }

        $version = $repository->version($item);
        if ($version !== null && ! $this->matches($ifMatch, $version)) {
            throw new VersionConflictException($this->serializeItem($user, $resource, $item, $this->mediaReferences($request)));
        }

        $uploads = $this->uploadResolver($user);
        $references = $this->mediaReferences($request);
        // The fields of the item's type, which never changes: the `type` field is left out of
        // forms, so its value is ignored
        $type = $resource->typeOf($item);
        $fields = $this->authorizer->writableFields($user, $resource, $type);
        $validated = Form::validate(
            $this->validator,
            $fields,
            $values,
            $this->validationContext(
                $request,
                FormMode::Update,
                uploads: $uploads,
                partial: true,
                item: $item,
                media: $references,
                valueExists: $this->checks->lookup($resource, $request, $item, $values, $references, $type),
            ),
            $resource->formLayout(),
        );
        $saving = new SaveContext(FormMode::Update, $item, $uploads, $agent);
        try {
            $validated = Form::prepare($fields, $validated, $saving);
            $validated = $resource->updating($item, $validated, $request);
            $item = $repository->update($item, $validated);
        } catch (Throwable $exception) {
            $saving->failed();

            throw $exception;
        }
        $saving->saved($item);
        $resource->updated($item, $validated, $request);
        $resource->saved($item, $validated, $request);

        return ['item' => $item, 'references' => $references];
    }

    /**
     * Whether an `If-Match` header matches a version. No header always matches: the check is
     * up to the client.
     */
    private function matches(?string $header, string $version): bool
    {
        if ($header === null || trim($header) === '') {
            return true;
        }
        foreach (explode(',', $header) as $tag) {
            $tag = trim($tag);
            if ($tag === '*') {
                return true;
            }
            if (str_starts_with($tag, 'W/')) {
                $tag = substr($tag, 2);
            }
            if (trim($tag, '"') === $version) {
                return true;
            }
        }

        return false;
    }
}
