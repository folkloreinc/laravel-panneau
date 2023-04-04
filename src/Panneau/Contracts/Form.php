<?php

namespace Panneau\Contracts;

use JsonSerializable;

interface Form extends JsonSerializable
{
    public function id(): string;

    public function name(): string;

    public function fields(): array;

    public function components(): ?array;

    public function settings(): ?array;

    public function attributes(): ?array;
}
