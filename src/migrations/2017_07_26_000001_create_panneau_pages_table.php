<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CreatePanneauPagesTable extends PanneauMigration
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

            if ($this->supportsJSON()) {
                $table->json('data')->nullable();
            } else {
                $table->longText('data')->nullable();
            }

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
