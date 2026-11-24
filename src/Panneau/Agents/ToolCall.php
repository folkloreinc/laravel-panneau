<?php

namespace Panneau\Agents;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * A call of a tool: its user, its arguments, and the request it was made in, which the
 * operations need for the scopes of the resources and the media of the values.
 */
final readonly class ToolCall
{
    /**
     * @param  array<string, mixed>  $arguments  As the agent sent them, decoded from JSON.
     */
    public function __construct(
        public ?Authenticatable $user,
        public array $arguments,
        public Request $request,
    ) {}
}
