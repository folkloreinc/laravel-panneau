<?php

namespace Panneau\Contracts\Document;

interface Factory
{
    public function document($name);

    public function documents();

    public function hasDocument($name);
}
