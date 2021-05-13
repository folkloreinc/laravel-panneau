<?php

namespace Panneau\Contracts;

use Illuminate\Support\Collection;

interface PanneauIntl extends Intl
{
    public function locale(): string;

    public function locales(): ?array;
}
