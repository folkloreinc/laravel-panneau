<?php

namespace Panneau\Contracts;

interface Panneau
{
    public function boot();

    public function booted($callback);

    public function serving($callback);

    public function definition(): Definition;

    public function resources(array $resources = null);

    public function resource($id): ?Resource;

    public function routes($options = []): void;

    public function router(): Router;
}
