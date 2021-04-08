<?php

namespace Panneau\Contracts;

use JsonSerializable;

interface ResourceType extends JsonSerializable
{
    public function id(): string;

    public function label(): string;

    public function fields(): array;

    public function resource(): Resource;

    public function repository(): ?Repository;

    public function newJsonResource(Item $resource): ?JsonSerializable;

    public function newJsonCollection($resources): ?JsonSerializable;
}
