<?php

namespace Folklore\Panneau\Support\Interfaces;

interface HasReducerSaving
{
    public function save($model, $name, $node, $state);
}
