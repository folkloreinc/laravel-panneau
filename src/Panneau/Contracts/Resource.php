<?php

namespace Panneau\Contracts;

use JsonSerializable;

interface Resource
{
    public function id(): string;

    public function name(): string;

    public function fields(): array;

    public function repository(): Repository;

    public function controller(): ?string;

    public function components(): ?array;

    public function messages(): array;

    public function newJsonResource(ResourceItem $resource): JsonSerializable;

    public function newJsonCollection($resources): JsonSerializable;
}
