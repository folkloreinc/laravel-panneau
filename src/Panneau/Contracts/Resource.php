<?php

namespace Panneau\Contracts;

use JsonSerializable;

interface Resource extends JsonSerializable
{
    public function id(): string;

    public function name(): string;

    public function types(): ?array;

    public function fields(): array;

    public function components(): ?array;

    public function intl(): ?Intl;

    public function settings(): ?array;

    public function routes(): ?array;

    public function makeController(): ?object;

    public function makeRepository(): Repository;

    public function makeJsonResource(ResourceItem $item): JsonSerializable;

    public function makeJsonCollection($items): JsonSerializable;
}
