<?php

namespace Folklore\Panneau\Support\Interfaces;

interface HasReducerGetter
{
    public function get($model, $name, $node, $state);
}
