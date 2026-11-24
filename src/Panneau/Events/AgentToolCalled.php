<?php

namespace Panneau\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Agents\AgentCaller;

/**
 * A tool that writes was called by an agent, and succeeded. Panneau keeps no log of its own: a
 * project listens to this event to keep one.
 */
final readonly class AgentToolCalled
{
    /**
     * @param  string  $tool  The name of the tool (`save_item`).
     * @param  string|null  $resource  The resource of the call, when it has one.
     * @param  list<string>  $items  The ids of the items the call created or changed.
     */
    public function __construct(
        public ?Authenticatable $user,
        public ?AgentCaller $agent,
        public string $tool,
        public ?string $resource,
        public array $items,
    ) {}
}
