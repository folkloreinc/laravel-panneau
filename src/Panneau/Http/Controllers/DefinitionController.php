<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Definition\DefinitionBuilder;

/**
 * `GET /definition`: the definition for the current user.
 *
 * @internal
 */
final class DefinitionController
{
    public function __construct(private readonly DefinitionBuilder $definition) {}

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->definition->build($request->user()));
    }
}
