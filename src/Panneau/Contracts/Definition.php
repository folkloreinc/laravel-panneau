<?php

namespace Panneau\Contracts;

use Illuminate\Support\Collection;
use JsonSerializable;

interface Definition extends JsonSerializable
{
    public function name(): string;

    public function locale(): string;

    public function messages(): Collection;

    public function resources(): Collection;

    public function routes(): Collection;
}
