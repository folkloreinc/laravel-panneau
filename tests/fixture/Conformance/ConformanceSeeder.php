<?php

namespace TestApp\Conformance;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use TestApp\Models\Author;
use TestApp\Models\Post;

class ConformanceSeeder extends Seeder
{
    public function run(): void
    {
        $author = Author::query()->create(['name' => 'Ada']);
        foreach (['First post', 'Second post', 'Third post'] as $index => $title) {
            Post::query()->create([
                'team_id' => 1,
                'title' => $title,
                'slug' => ['fr' => 'article-'.($index + 1), 'en' => Str::slug($title)],
                'subtitle' => ['fr' => 'Article '.($index + 1), 'en' => 'Post '.($index + 1)],
                'status' => 'published',
                'author_id' => $author->id,
                'published_at' => now()->subDays($index),
            ]);
        }
    }
}
