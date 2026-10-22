<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id')->nullable();
            $table->foreignId('author_id')->nullable();
            $table->json('subtitle')->nullable();
            $table->json('blocks')->nullable();
            $table->json('seo')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->text('body')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('secret')->nullable();
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id');
            $table->foreignId('tag_id');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Media of the Eloquent libraries: ImagesResource and DocumentsResource
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id')->nullable();
            $table->string('library')->nullable();
            $table->string('type')->nullable()->index();
            $table->json('file')->nullable();
            $table->string('name')->nullable();
            $table->json('alt')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('cover')->nullable();
            $table->json('gallery')->nullable();
            $table->string('attachment')->nullable();
            $table->json('blocks')->nullable();
            $table->timestamps();
        });

        // Searched through Laravel Scout: NotesResource
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('terms')->nullable();
            $table->json('cover')->nullable();
            $table->json('attachments')->nullable();
            $table->json('sections')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('authors');
    }
};
