<?php

namespace Panneau\Operations;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Auth\Authorizer;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Forms\SaveContext;
use Panneau\Media\MediaReferences;
use Panneau\Operations\Concerns\ResolvesItems;
use Panneau\Operations\Concerns\ValidatesValues;
use Panneau\Resources\FieldChecks;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Creates an item, as `POST /resources/{resource}` does: the user must be able to create
 * (`create`), and an item of its type in a resource with types. Only the values of the fields
 * the user can edit are validated and saved.
 */
final class CreateItem
{
    use ResolvesItems;
    use ValidatesValues;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly DefinitionBuilder $definition,
        private readonly ValidationFactory $validator,
        private readonly Uploads $uploads,
        private readonly FieldChecks $checks,
    ) {}

    /**
     * @param  array<string, mixed>  $values  The values of the new item, as in the body of the
     *                                        request.
     * @param  Request  $request  For the scope of the resource, the media of the values and the
     *                            hooks of the resource.
     * @return array{item: object, references: MediaReferences} The new item, and the media found
     *                                                          while validating it, to serialize it.
     *
     * @throws NotFoundHttpException When the resource doesn't exist.
     * @throws AuthorizationException When the user can't create the item.
     * @throws ValidationException When the values aren't valid.
     */
    public function handle(?Authenticatable $user, Resource|string $resource, array $values, Request $request): array
    {
        $resource = $this->findResource($resource);
        if (! $this->authorizer->allowsResource($user, $resource, 'create')) {
            throw new AuthorizationException;
        }
        $type = $this->creatableType($user, $resource, $values);

        $uploads = $this->uploadResolver($user);
        $references = $this->mediaReferences($request);
        // The values of fields the user can't see or edit are ignored (panneau-js decision 0046)
        $fields = $this->authorizer->writableFields($user, $resource, $type);
        $validated = Form::validate(
            $this->validator,
            $fields,
            $values,
            $this->validationContext(
                $request,
                FormMode::Create,
                uploads: $uploads,
                media: $references,
                valueExists: $this->checks->lookup($resource, $request, null, $values, $references, $type),
            ),
            $resource->formLayout(),
        );
        $saving = new SaveContext(FormMode::Create, null, $uploads);
        try {
            $validated = Form::prepare($fields, $validated, $saving);
            if ($type !== null) {
                $validated[Resource::ITEM_TYPE] = $type;
            }
            $validated = $resource->creating($validated, $request);
            $item = $this->resources->repository($resource)->create($validated, $resource->repositoryScope($request));
        } catch (Throwable $exception) {
            $saving->failed();

            throw $exception;
        }
        $saving->saved($item);
        $resource->created($item, $validated, $request);
        $resource->saved($item, $validated, $request);

        return ['item' => $item, 'references' => $references];
    }

    /**
     * The type of a new item, in a resource with types (panneau-js decision 0077): the `type` of
     * the values, one of the resource's types, that the user can create. Null for a resource
     * without types.
     *
     * @param  array<string, mixed>  $values
     */
    private function creatableType(?Authenticatable $user, Resource $resource, array $values): ?string
    {
        if (! $resource->hasTypes()) {
            return null;
        }
        $id = $values[Resource::ITEM_TYPE] ?? null;
        $type = is_string($id) ? $resource->resourceType($id) : null;
        if ($type === null) {
            throw ValidationException::withMessages([
                Resource::ITEM_TYPE => [__('The type is not a type of this resource.')],
            ]);
        }
        if (! $type->canCreate($user)) {
            throw new AuthorizationException(__('Items of this type can\'t be created.'));
        }

        return $type->id();
    }
}
