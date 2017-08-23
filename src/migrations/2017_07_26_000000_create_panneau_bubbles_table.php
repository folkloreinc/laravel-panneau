<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePanneauBubblesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('panneau.table_prefix').'bubbles', function (Blueprint $table) {
            // Standard columns
            $table->increments('id');
            $table->json('data')->nullable();

            // Generated columns
            $table->string('type')->nullable()->storedAs('data->>"$.type"');
            $table->string('slug')->nullable()->storedAs('data->>"$.slug"');
            $table->integer('parent_id')->nullable()->storedAs('data->"$.parent"');
            $table->integer('order')->nullable()->storedAs('data->"$.order"');

            // Laravel features
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('panneau.table_prefix').'bubbles');
    }
}
