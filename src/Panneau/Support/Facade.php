<?php

namespace Panneau\Support;

use Illuminate\Support\Facades\Facade as BaseFacade;
use Panneau\Panneau;

/**
 * @method static \Panneau\Contracts\Panneau repositoryAdapter(string $contract, string|\Closure $adapter)
 *
 * @see Panneau
 */
class Facade extends BaseFacade
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
