<?php

namespace Panneau\Agents;

/**
 * A tool that an agent calls as its user (decisions 0022 and 0025): `describe`, `find_items`,
 * `get_item`… Its arguments and results are those of the protocol's `agents` schemas. The MCP
 * server and the in-app assistant serve the same tools.
 */
interface Tool
{
    /**
     * The name of the tool in the protocol: `find_items`.
     */
    public function name(): string;

    /**
     * What the tool does, written for a model.
     */
    public function description(): string;

    /**
     * The JSON Schema of the arguments.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Whether the tool only reads.
     */
    public function readOnly(): bool;

    /**
     * Whether a call can delete or overwrite more than what it is given: `destructiveHint` over
     * MCP. Only meaningful for a tool that writes.
     */
    public function destructive(): bool;

    /**
     * Runs the tool. An error is a result, never an exception.
     */
    public function handle(ToolCall $call): ToolResult;
}
