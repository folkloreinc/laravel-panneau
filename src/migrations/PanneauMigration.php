<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PanneauMigration extends Migration
{
    protected function supportsJSON()
    {
        $supportsJSON = env('PANNEAU_MYSQL_SUPPORTS_JSON', null);
        if (!is_null($supportsJSON)) {
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
