<?php

namespace Panneau\Operations\Concerns;

use Illuminate\Http\Request;
use Panneau\Media\MediaReferences;
use Panneau\Resources\Resource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Finds the resources and items of the operations, and the media of their values. The class
 * that uses it has `$resources`.
 *
 * @internal
 */
trait ResolvesItems
{
    protected function findResource(Resource|string $resource): Resource
    {
        if ($resource instanceof Resource) {
            return $resource;
        }

        return $this->resources->find($resource) ?? throw new NotFoundHttpException(sprintf('No resource "%s".', $resource));
    }

    protected function findItem(Resource $resource, string $id, Request $request): object
    {
        return $this->resources->repository($resource)->find($id, $resource->repositoryScope($request))
            ?? throw new NotFoundHttpException;
    }

    /**
     * Finds the media of `media` values, within the scope of their library, once for a request:
     * validation and serialization ask for the same media.
     */
    protected function mediaReferences(Request $request): MediaReferences
    {
        return new MediaReferences($this->resources, $request);
    }
}
