<?php

namespace Panneau\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Panneau\Agents\AgentCaller;
use Panneau\Contracts\ResourceRepository;
use Panneau\Resources\Resource;

/**
 * What an action runs in.
 */
final readonly class ActionContext
{
    /**
     * @param  array<string, mixed>  $scope  The resource's scope for this request.
     * @param  AgentCaller|null  $agent  The connection of the agent that runs the action
     *                                   through a tool, or null outside the tools.
     */
    public function __construct(
        public Resource $resource,
        public ResourceRepository $repository,
        public array $scope,
        public Request $request,
        public ?Authenticatable $user,
        public ?AgentCaller $agent = null,
    ) {}
}
