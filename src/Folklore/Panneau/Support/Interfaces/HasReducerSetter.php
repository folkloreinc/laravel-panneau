<?php

namespace Folklore\Panneau\Support\Interfaces;

interface HasReducerSetter
{
    public function set($model, $node, $state);
}