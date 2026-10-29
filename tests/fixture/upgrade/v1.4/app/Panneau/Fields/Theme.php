<?php

namespace App\Panneau\Fields;

use Panneau\Fields\Radios;

class Theme extends Radios
{
    public function options(): array
    {
        return [['value' => 'light', 'label' => 'Light'], ['value' => 'dark', 'label' => 'Dark']];
    }
}
