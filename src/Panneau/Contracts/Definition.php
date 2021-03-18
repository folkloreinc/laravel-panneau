<?php

namespace Panneau\Contracts;

use Illuminate\Support\Collection;

interface Definition
{
    public function name(): string;

    public function locale(): string;

    public function messages(): Collection;

    public function resources(): Collection;

    public function routes(): Collection;
}
