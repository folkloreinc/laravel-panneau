<?php

namespace Panneau\Contracts;

use Illuminate\Support\Collection;
use JsonSerializable;

interface Definition extends JsonSerializable
{
    public function name(): string;

    public function routes(): Collection;

    public function resources(): Collection;

    public function intl(): PanneauIntl;

    public function settings(): ?array;
}
