<?php

namespace Panneau\Contracts;

interface PanneauIntl extends Intl
{
    public function locale(): string;

    public function locales(): ?array;
}
