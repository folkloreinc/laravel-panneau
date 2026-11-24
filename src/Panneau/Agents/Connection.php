<?php

namespace Panneau\Agents;

use DateTimeInterface;

/**
 * A connection of a user to the MCP server (decision 0029): a personal token, or an OAuth client
 * the user authorized. Its id is opaque: `token:{id}` or `client:{id}`.
 */
final readonly class Connection
{
    public const TOKEN = 'token';

    public const CLIENT = 'client';

    /**
     * @param  self::TOKEN|self::CLIENT  $type
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public bool $write,
        public DateTimeInterface $createdAt,
        public ?DateTimeInterface $lastUsedAt = null,
        public ?DateTimeInterface $expiresAt = null,
    ) {}

    /**
     * The connection in the answers of the account, with dates in ISO 8601.
     *
     * @return array{id: string, type: string, name: string, write: bool, createdAt: string, lastUsedAt: string|null, expiresAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'write' => $this->write,
            'createdAt' => $this->createdAt->format(DateTimeInterface::ATOM),
            'lastUsedAt' => $this->lastUsedAt?->format(DateTimeInterface::ATOM),
            'expiresAt' => $this->expiresAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
