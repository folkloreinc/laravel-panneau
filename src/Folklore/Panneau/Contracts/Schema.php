<?php

namespace Folklore\Panneau\Contracts;

interface Schema
{
    public function getType();

    public function setType($type);

    public function getProperties();

    public function setProperties($properties);

    public function getAttributes();

    public function setAttributes($attributes);

    public function toArray();
}
