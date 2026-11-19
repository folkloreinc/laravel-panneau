<?php

namespace Panneau\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
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
    public function __construct(
        private readonly DefinitionBuilder $definition,
        private readonly Application $app,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $response = new JsonResponse($this->definition->build($user));
        // A version of the definition, by user, context and locale (#246). The protocol has no
        // 304 for this operation yet: the definition is always sent.
        $response->setEtag(self::etag($response, $user, $request, $this->app->getLocale()));

        return $response;
    }

    private static function etag(JsonResponse $response, ?Authenticatable $user, Request $request, string $locale): string
    {
        $id = $user?->getAuthIdentifier();

        return hash('xxh128', implode("\0", [
            $user !== null && is_scalar($id) ? $user::class.':'.$id : '',
            (string) $request->header('Panneau-Context', ''),
            $locale,
            (string) $response->getContent(),
        ]));
    }
}
