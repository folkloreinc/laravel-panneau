<?php

namespace Panneau\Contracts;

use JsonSerializable;

interface ResourceType extends JsonSerializable
{
    public function id(): string;

    public function name(): string;

    public function fields(): array;

    public function resource(): Resource;

    public function settings(): ?array;

    public function makeRepository(): ?Repository;

    public function makeJsonResource(ResourceItem $item): ?JsonSerializable;

    public function makeJsonCollection($resources): ?JsonSerializable;
}
