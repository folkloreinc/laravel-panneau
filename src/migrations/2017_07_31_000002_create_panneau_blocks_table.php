<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Folklore\Panneau\Support\Migration;

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

            if ($this->supportsJSON()) {
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
