<?php

namespace TestApp\Conformance;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Models\Run;

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
        // Runs, counted, summed and grouped by the kit (panneau-js decision 0084)
        foreach (['succeeded', 'failed', 'succeeded', null] as $index => $status) {
            $run = Run::query()->create([
                'team_id' => 1,
                'title' => 'Run '.($index + 1),
                'status' => $status,
                'tool' => $index < 2 ? 'import' : 'export',
                'author_id' => $index < 3 ? $author->id : null,
                'labels' => $index === 0 ? ['urgent', 'night'] : [],
                'cost' => 1.25 * $index,
                'tokens' => 100 * $index,
                'amount' => ['amount' => 1500 * $index, 'currency' => 'CAD'],
                'ran_at' => $index < 3 ? now()->subDays($index * 3) : null,
                'day' => now()->subDays($index)->toDateString(),
            ]);
            $run->watchers()->sync($index < 2 ? [$author->id] : []);
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
