<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePanneauBlocksMorphablePivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('panneau.table_prefix').'blocks_morphable_pivot', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('block_id')->unsigned();
            $table->morphs('morphable');
            $table->string('handle')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('block_id');
            $table->index('order');
            $table->index('handle');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('panneau.table_prefix').'blocks_morphable_pivot');
    }
}
