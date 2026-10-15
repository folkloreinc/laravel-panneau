<?php

namespace Panneau\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Panneau\Contracts\ResourceRepository;
use Panneau\Resources\Resource;

/**
 * What an action runs in.
 */
final readonly class ActionContext
{
    /**
     * @param  array<string, mixed>  $scope  The resource's scope for this request.
     */
    public function __construct(
        public Resource $resource,
        public ResourceRepository $repository,
        public array $scope,
        public Request $request,
        public ?Authenticatable $user,
    ) {}
}
