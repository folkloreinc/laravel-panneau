<?php

namespace Panneau\Contracts;

use Illuminate\Support\Collection;
use JsonSerializable;

interface Resource extends JsonSerializable
{
    public function id(): string;

    public function name(): string;

    public function fields(): array;

    public function types(): ?Collection;

    public function repository(): Repository;

    public function controller(): ?string;

    public function components(): ?array;

    public function messages(): array;

    public function newJsonResource(ResourceItem $resource): JsonSerializable;

    public function newJsonCollection($resources): JsonSerializable;
}
