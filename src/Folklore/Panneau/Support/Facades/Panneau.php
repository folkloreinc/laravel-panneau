<?php namespace Folklore\Panneau\Support\Facades;

use Illuminate\Support\Facades\Facade;

class Panneau extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'panneau';
    }
}
