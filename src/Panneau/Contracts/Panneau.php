<?php

namespace Panneau\Contracts;

interface Panneau
{
    public function routes($options = []);

    public function resources(array $resources = null);

    public function definition(): Definition;

    public function router();
}
