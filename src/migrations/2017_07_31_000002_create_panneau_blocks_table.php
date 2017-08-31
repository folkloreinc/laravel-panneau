<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePanneauBlocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('panneau.table_prefix').'blocks', function (Blueprint $table) {
            // Standard columns
            $table->increments('id');
            $table->string('type')->nullable();

            $pdo = DB::connection()->getPdo();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql' &&
                version_compare($pdo->getAttribute(PDO::ATTR_SERVER_VERSION), '5.7.8', 'ge')
            ) {
                $table->json('data')->nullable();
            } else {
                $table->longText('data')->nullable();
            }

            // Laravel features
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('panneau.table_prefix').'blocks');
    }
}
