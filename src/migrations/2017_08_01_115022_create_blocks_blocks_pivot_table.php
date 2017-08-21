<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBlocksBlocksPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('blocks_blocks_pivot', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_block_id')->unsigned();
            $table->integer('block_id')->unsigned();
            $table->integer('order')->default(0);

            $table->index('parent_block_id');
            $table->index('block_id');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blocks_blocks_pivot');
    }
}
