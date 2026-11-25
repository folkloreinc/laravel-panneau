<?php

namespace Panneau\Console;

use Composer\InstalledVersions;

/**
 * The Composer packages of the project, for the installer.
 *
 * @internal
 */
class Packages
{
    public function installed(string $package): bool
    {
        return InstalledVersions::isInstalled($package);
    }

    /**
     * The installed version, `13.1.0.0` or `dev-main`, null when it isn't installed.
     */
    public function version(string $package): ?string
    {
        return $this->installed($package) ? InstalledVersions::getVersion($package) : null;
    }
}
