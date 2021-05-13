<?php

namespace Panneau\Contracts;

use Illuminate\Support\Collection;
use JsonSerializable;

interface Intl extends JsonSerializable
{
    public function values(): ?array;

    public function messages(): ?array;
}
