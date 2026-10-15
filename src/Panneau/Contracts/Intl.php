<?php

namespace Panneau\Contracts;

use JsonSerializable;

interface Intl extends JsonSerializable
{
    public function values(): ?array;

    public function messages(): ?array;
}
