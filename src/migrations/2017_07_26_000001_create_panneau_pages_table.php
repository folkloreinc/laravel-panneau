<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Folklore\Panneau\Support\Migration;

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
            $table->string('type')->nullable();
            $table->string('handle')->nullable();
            $table->integer('parent_id')->nullable();
            $table->integer('order')->nullable();
            $table->longText('data')->nullable();

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
