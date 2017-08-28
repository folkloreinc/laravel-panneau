<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePanneauPagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('panneau.table_prefix').'pages', function (Blueprint $table) {
            // Standard columns
            $table->increments('id');
            $table->string('type')->nullable()/*->storedAs('data->>"$.type"')*/;
            $table->string('handle')->nullable()/*->storedAs('data->>"$.slug"')*/;
            $table->integer('parent_id')->nullable()/*->storedAs('data->"$.parent"')*/;
            $table->integer('order')->nullable()/*->storedAs('data->"$.order"')*/;
            $table->json('data')->nullable();

            // Laravel features
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('handle');
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
        Schema::dropIfExists(config('panneau.table_prefix').'pages');
    }
}
