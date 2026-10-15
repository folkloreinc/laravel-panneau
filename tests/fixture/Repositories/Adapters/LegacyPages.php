<?php

namespace TestApp\Repositories\Adapters;

class LegacyPages implements LegacyRepository
{
    public function all(): array
    {
        return ['1' => (object) ['id' => '1', 'title' => 'Home']];
    }
}
