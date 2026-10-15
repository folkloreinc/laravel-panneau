<?php

namespace TestApp\Repositories\Adapters;

/**
 * A repository contract of a project, which Panneau doesn't know.
 */
interface LegacyRepository
{
    /**
     * @return array<string, object>
     */
    public function all(): array;
}
