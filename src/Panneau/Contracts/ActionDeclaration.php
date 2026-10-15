<?php

namespace Panneau\Contracts;

/**
 * An action of a resource, as declared in the definition.
 */
interface ActionDeclaration
{
    public function id(): string;

    /**
     * The action in the protocol's definition, without its abilities.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array;
}
