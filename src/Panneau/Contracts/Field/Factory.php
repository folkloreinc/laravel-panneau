<?php

namespace Panneau\Contracts\Field;

interface Factory
{
    public function field($name);

    public function fields();

    public function hasField($name);
}
