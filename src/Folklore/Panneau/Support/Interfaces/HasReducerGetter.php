<?php

namespace Folklore\Panneau\Support\Interfaces;

interface HasReducerGetter
{
    public function get($model, $path, $node, $state);
}
