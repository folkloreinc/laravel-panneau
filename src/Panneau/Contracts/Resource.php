<?php

namespace Panneau\Contracts;

interface Resource
{
    public function id(): string;

    public function label(): string;

    public function fields(): array;

    public function repository(): Repository;

    public function controller(): ?string;

    public function components(): ?array;

    public function messages(): array;

    public function newJsonResource(Item $resource): JsonSerializable;

    public function newJsonCollection($resources): JsonSerializable;
}
