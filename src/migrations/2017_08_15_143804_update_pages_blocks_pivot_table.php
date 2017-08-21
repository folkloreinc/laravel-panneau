<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePagesBlocksPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pages_blocks_pivot', function (Blueprint $table) {
            $table->string('relation');
        });

        DB::table('pages_blocks_pivot')->update(['relation' => 'blocks']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pages_blocks_pivot', function (Blueprint $table) {
            $table->dropColumn('relation');
        });
    }
}
