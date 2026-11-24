<?php

namespace Panneau\Agents;

/**
 * The connection an agent calls the tools through, as the user: for a personal token of
 * Sanctum or Passport, the token's id and name. Fields, hooks and revisions can record it
 * (`SaveContext::$agent`), and `AgentToolCalled` carries it.
 */
final readonly class AgentCaller
{
    public function __construct(
        public string $id,
        public ?string $name = null,
    ) {}

    /**
     * The token the user came with, from `currentAccessToken()`, or null when there is none or
     * it isn't a stored token (the transient token of a session, for example).
     */
    public static function fromUser(?object $user): ?self
    {
        $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if (! is_object($token)) {
            return null;
        }
        $id = method_exists($token, 'getKey') ? $token->getKey() : ($token->id ?? null);
        if (! is_int($id) && ! is_string($id)) {
            return null;
        }
        $name = $token->name ?? null;

        return new self((string) $id, is_string($name) ? $name : null);
    }
}
