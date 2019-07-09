<?php

namespace Panneau\Contracts\Block;

interface Factory
{
    public function block($name);

    public function blocks();

    public function hasBlock($name);
}
