<?php

namespace Panneau\Agents\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Panneau\Http\Middleware\AuthorizeAgents;

/**
 * The OAuth metadata of laravel/mcp, with the scopes of Panneau: `mcp:use` reads, and
 * `panneau:write` also writes. Every other key is the package's.
 *
 * @internal
 */
final class OAuthMetadata extends Registrar
{
    public const SCOPES = [AuthorizeAgents::USE, AuthorizeAgents::WRITE];

    public function protectedResource(Request $request): JsonResponse
    {
        $path = $request->route('path');
        $metadata = self::protectedResourceMetadata(is_string($path) ? $path : '');
        $metadata['scopes_supported'] = self::SCOPES;

        return new JsonResponse($metadata);
    }

    public function authorizationServer(): JsonResponse
    {
        $metadata = self::authorizationServerMetadata('oauth');
        $metadata['scopes_supported'] = self::SCOPES;

        return new JsonResponse($metadata);
    }
}
