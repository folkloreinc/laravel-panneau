<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePanneauPagesSlugColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(config('panneau.table_prefix').'pages', function (Blueprint $table) {
            $locales = panneau()->locales();
            $lastColumn = 'data';
            foreach ($locales as $locale) {
                $table->string('slug_'.$locale)->after($lastColumn)->nullable();
                $lastColumn = 'slug_'.$locale;
                $table->index('slug_'.$locale);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(config('panneau.table_prefix').'pages', function (Blueprint $table) {
            $locales = panneau()->locales();
            foreach ($locales as $locale) {
                $table->dropColumn('slug_'.$locale);
            }
        });
    }
}
