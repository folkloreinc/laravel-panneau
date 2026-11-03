<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The roles and grants of users, for the default store of the roles module (decision 0014). A
 * user is a morph type and an id, so any user model works. Roles and permissions are names from
 * config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('panneau.roles.connection'))->create('panneau_role_user', function (Blueprint $table) {
            $table->id();
            $table->string('user_type');
            $table->string('user_id');
            $table->string('role');
            $table->timestamps();
            $table->unique(['user_type', 'user_id', 'role']);
        });
        Schema::connection(config('panneau.roles.connection'))->create('panneau_permission_user', function (Blueprint $table) {
            $table->id();
            $table->string('user_type');
            $table->string('user_id');
            $table->string('permission');
            $table->timestamps();
            $table->unique(['user_type', 'user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('panneau.roles.connection'))->dropIfExists('panneau_permission_user');
        Schema::connection(config('panneau.roles.connection'))->dropIfExists('panneau_role_user');
    }
};
