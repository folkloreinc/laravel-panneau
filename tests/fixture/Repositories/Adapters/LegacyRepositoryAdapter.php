<?php

namespace TestApp\Repositories\Adapters;

/**
 * Turns any LegacyRepository into a ResourceRepository.
 */
class LegacyRepositoryAdapter extends ArrayRepository
{
    public function __construct(public LegacyRepository $repository)
    {
        parent::__construct($repository->all());
    }
}
