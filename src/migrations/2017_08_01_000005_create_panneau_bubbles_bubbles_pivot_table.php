<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePanneauBubblesBubblesPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('panneau.table_prefix').'bubbles_bubbles_pivot', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_bubble_id')->unsigned();
            $table->integer('bubble_id')->unsigned();
            $table->string('handle')->nullable();
            $table->integer('order')->default(0);

            $table->index('parent_bubble_id');
            $table->index('bubble_id');
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
        Schema::dropIfExists(config('panneau.table_prefix').'bubbles_bubbles_pivot');
    }
}
