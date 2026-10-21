<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panneau_uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('uid')->unique();
            $table->string('user_id');
            $table->string('status', 16)->index();
            $table->string('disk');
            $table->string('path', 1024);
            $table->string('provider_upload_id', 1024)->nullable();
            $table->string('name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panneau_uploads');
    }
};
