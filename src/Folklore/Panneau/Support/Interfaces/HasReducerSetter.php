<?php

namespace Folklore\Panneau\Support\Interfaces;

interface HasReducerSetter
{
    public function set($model, $path, $node, $state);
}
