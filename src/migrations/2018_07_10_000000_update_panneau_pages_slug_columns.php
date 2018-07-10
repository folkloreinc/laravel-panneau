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
            $locales = config('locale.locales', [config('app.locale')]);
            foreach ($locales as $locale) {
                $table->string('slug_'.$locale)->nullable();
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
            $locales = config('locale.locales', [config('app.locale')]);
            foreach ($locales as $locale) {
                $table->dropColumn('slug_'.$locale);
            }
        });
    }
}
