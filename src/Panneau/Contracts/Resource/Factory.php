<?php

namespace Panneau\Contracts\Resource;

interface Factory
{
    public function resource($name);

    public function resources();

    public function hasResource($name);
}
