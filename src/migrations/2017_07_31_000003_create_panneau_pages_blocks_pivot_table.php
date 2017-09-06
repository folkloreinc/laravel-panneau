<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CreatePanneauPagesBlocksPivotTable extends PanneauMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('panneau.table_prefix').'pages_blocks_pivot', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('page_id')->unsigned();
            $table->integer('block_id')->unsigned();
            $table->string('handle')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('block_id');
            $table->index('page_id');
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
        Schema::dropIfExists(config('panneau.table_prefix').'pages_blocks_pivot');
    }
}
