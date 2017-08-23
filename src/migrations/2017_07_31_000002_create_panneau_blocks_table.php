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
            $table->json('data')->nullable();

            // Generated columns
            $table->string('type')->nullable()->storedAs('data->>"$.type"');

            // Laravel features
            $table->timestamps();
            $table->softDeletes();

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
