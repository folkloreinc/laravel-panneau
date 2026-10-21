<?php

namespace Panneau\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Panneau\Auth\Authorizer;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Exceptions\VersionConflictException;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Forms\SaveContext;
use Panneau\Http\Controllers\Concerns\ResolvesResources;
use Panneau\Media\MediaReferences;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use Panneau\Uploads\Uploads;
use Throwable;

/**
 * The resource endpoints: list, show, create, update and delete.
 *
 * @internal
 */
final class ResourceController
{
    use ResolvesResources;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
        private readonly DefinitionBuilder $definition,
        private readonly ValidationFactory $validator,
        private readonly Config $config,
        private readonly Uploads $uploads,
    ) {}

    public function index(Request $request, string $resource): JsonResponse
    {
        $resource = $this->findResource($resource);
        $this->authorizeResource($request, $resource, 'viewAny');

        $query = ResourceQuery::fromParameters(
            $request->query(),
            $this->resources->queryOptions(
                $resource,
                (int) $this->config->get('panneau.list.per_page', 20),
                (int) $this->config->get('panneau.list.max_per_page', 100),
            ),
            $resource->repositoryScope($request),
        );
        $page = $this->resources->repository($resource)->query($query);
        $items = array_values($page->items());
        $references = $this->mediaReferences($request);
        $references->preload($resource, $items);

        return new JsonResponse([
            'data' => array_map(
                fn (object $item): array => $this->item($request, $resource, $item, $query->fields, $references),
                $items,
            ),
            'meta' => [
                'page' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => max(1, $page->lastPage()),
            ],
        ]);
    }

    public function show(Request $request, string $resource, string $id): JsonResponse
    {
        $resource = $this->findResource($resource);
        $item = $this->findItem($resource, $id, $request);
        $this->authorizeItem($request, $resource, 'view', $item);

        return $this->itemResponse($request, $resource, $item);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $resource = $this->findResource($resource);
        $this->authorizeResource($request, $resource, 'create');

        $uploads = $this->uploadResolver($request);
        $references = $this->mediaReferences($request);
        $values = Form::validate(
            $this->validator,
            $resource->allFields(),
            $request->all(),
            $this->validationContext($request, FormMode::Create, uploads: $uploads, media: $references),
        );
        $saving = new SaveContext(FormMode::Create, null, $uploads);
        try {
            $values = Form::prepare($resource->allFields(), $values, $saving);
            $values = $resource->creating($values, $request);
            $item = $this->resources->repository($resource)->create($values, $resource->repositoryScope($request));
        } catch (Throwable $exception) {
            $saving->failed();

            throw $exception;
        }
        $saving->saved($item);
        $resource->created($item, $values, $request);
        $resource->saved($item, $values, $request);

        return $this->itemResponse($request, $resource, $item, 201, $references);
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        $resource = $this->findResource($resource);
        $repository = $this->resources->repository($resource);
        $item = $this->findItem($resource, $id, $request);
        $this->authorizeItem($request, $resource, 'update', $item);

        $version = $repository->version($item);
        if ($version !== null && ! $this->matches($request->header('If-Match'), $version)) {
            throw new VersionConflictException($this->item($request, $resource, $item));
        }

        $uploads = $this->uploadResolver($request);
        $references = $this->mediaReferences($request);
        $values = Form::validate(
            $this->validator,
            $resource->allFields(),
            $request->all(),
            $this->validationContext($request, FormMode::Update, partial: true, item: $item, uploads: $uploads, media: $references),
        );
        $saving = new SaveContext(FormMode::Update, $item, $uploads);
        try {
            $values = Form::prepare($resource->allFields(), $values, $saving);
            $values = $resource->updating($item, $values, $request);
            $item = $repository->update($item, $values);
        } catch (Throwable $exception) {
            $saving->failed();

            throw $exception;
        }
        $saving->saved($item);
        $resource->updated($item, $values, $request);
        $resource->saved($item, $values, $request);

        return $this->itemResponse($request, $resource, $item, references: $references);
    }

    public function destroy(Request $request, string $resource, string $id): Response
    {
        $resource = $this->findResource($resource);
        $item = $this->findItem($resource, $id, $request);
        $this->authorizeItem($request, $resource, 'delete', $item);

        $resource->deleting($item);
        $this->resources->repository($resource)->delete($item);
        $resource->deleted($item);

        return new Response(status: 204);
    }

    /**
     * @param  list<string>|null  $fields
     * @return array<string, mixed>
     */
    private function item(Request $request, Resource $resource, object $item, ?array $fields = null, ?MediaReferences $references = null): array
    {
        return $this->serializer->serialize(
            $resource,
            $item,
            $fields,
            $this->authorizer->itemAbilities($request->user(), $resource, $item),
            $references ?? $this->mediaReferences($request),
        );
    }

    private function itemResponse(Request $request, Resource $resource, object $item, int $status = 200, ?MediaReferences $references = null): JsonResponse
    {
        $data = $this->item($request, $resource, $item, references: $references);
        $headers = is_string($data['version']) ? ['ETag' => '"'.$data['version'].'"'] : [];

        return new JsonResponse(['data' => $data], $status, $headers);
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
