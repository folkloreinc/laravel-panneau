<?php

namespace Panneau\Support;

use Illuminate\Support\Facades\Facade as BaseFacade;
use Panneau\Panneau;

/**
 * @method static \Panneau\Contracts\Panneau resources(array $resources)
 * @method static \Panneau\Resources\Resource|null resource(string $id)
 * @method static \Panneau\Contracts\Panneau repositoryAdapter(string $contract, string|\Closure $adapter)
 * @method static \Panneau\Contracts\Panneau allowWithoutPolicies(bool $allow = true)
 * @method static void routes()
 * @method static array<string, mixed> definition(?\Illuminate\Contracts\Auth\Authenticatable $user = null)
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
