<?php

namespace Panneau\Agents;

/**
 * A personal token just made for the MCP server: its plain text, shown once, and its connection.
 */
final readonly class ConnectionToken
{
    public function __construct(
        public string $plainTextToken,
        public Connection $connection,
    ) {}
}
