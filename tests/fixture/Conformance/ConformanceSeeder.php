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
                // A range (open at its end for the last one) and an amount (decision 0053)
                'dates' => ['start' => now()->addDays($index)->toAtomString(), 'end' => $index < 2 ? now()->addDays($index + 2)->toAtomString() : null],
                'price' => ['amount' => 1250 * ($index + 1), 'currency' => 'CAD'],
            ]);
        }
        // A post the user can't update or delete (PostPolicy), for the checks of item abilities
        Post::query()->create([
            'team_id' => 1,
            'title' => 'Locked',
            'slug' => ['fr' => 'verrouille', 'en' => 'locked'],
            'subtitle' => ['fr' => 'Verrouillé', 'en' => 'Locked'],
            'status' => 'published',
            'author_id' => $author->id,
            'published_at' => now()->subDays(3),
        ]);
    }
}
