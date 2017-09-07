<?php

namespace Folklore\Panneau\Support;

use Illuminate\Database\Migrations\Migration as BaseMigration;
use Illuminate\Support\Facades\DB;
use PDO;

class Migration extends BaseMigration
{
    protected function supportsJSON()
    {
        $supportsJSON = config('panneau.migrations_supports_json', 'detect');
        if ($supportsJSON !== 'detect') {
            return $supportsJSON;
        }
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql' &&
            version_compare($pdo->getAttribute(PDO::ATTR_SERVER_VERSION), '5.7.8', 'ge')
        ) {
            return true;
        } else {
            return false;
        }
    }
}
