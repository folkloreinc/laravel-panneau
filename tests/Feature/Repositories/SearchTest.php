<?php

namespace Panneau\Tests\Feature\Repositories;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Panneau\Query\ResourceQuery;
use Panneau\Query\SearchTerms;
use Panneau\Repositories\EloquentRepository;
use Panneau\Tests\TestCase;
use TestApp\Models\SearchEntry;

/**
 * The search of `EloquentRepository` without Scout: every word, in any field, whatever its case
 * and its accents. It runs on SQLite, and `MySqlSearchTest`, `MariaDbSearchTest` and
 * `PgsqlSearchTest` run the same tests on their database.
 */
class SearchTest extends TestCase
{
    /**
     * The driver of the connection the tests must run on.
     */
    protected const DRIVER = 'sqlite';

    /**
     * The environment variable that holds the URL of the database (`mysql://user:password@host:port/database`),
     * or null for the default connection of the suite. Without the variable, the tests are skipped.
     */
    protected const URL = null;

    /**
     * @var EloquentRepository<SearchEntry>
     */
    private EloquentRepository $entries;

    protected function setUp(): void
    {
        if (static::URL !== null && self::url() === null) {
            $this->markTestSkipped(sprintf('Set %s to the URL of a %s database to run this test.', static::URL, static::DRIVER));
        }

        parent::setUp();

        $this->assertSame(static::DRIVER, Schema::getConnection()->getDriverName());
        $binary = in_array(static::DRIVER, ['mysql', 'mariadb'], true);
        Schema::dropIfExists('panneau_search_entries');
        Schema::create('panneau_search_entries', function (Blueprint $table) use ($binary): void {
            $table->id();
            $title = $table->string('title');
            $body = $table->text('body')->nullable();
            $table->json('name')->nullable();
            if ($binary) {
                // The search must not count on the collation of the columns
                $title->collation('utf8mb4_bin');
                $body->collation('utf8mb4_bin');
            }
        });
        $this->entries = EloquentRepository::for(SearchEntry::class);
    }

    protected function tearDown(): void
    {
        if ($this->app !== null) {
            Schema::dropIfExists('panneau_search_entries');
        }

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $url = self::url();
        if ($url !== null) {
            $app['config']->set('database.connections.panneau_search', [
                'driver' => static::DRIVER,
                'url' => $url,
                'charset' => static::DRIVER === 'pgsql' ? 'utf8' : 'utf8mb4',
                'prefix' => '',
            ]);
            $app['config']->set('database.default', 'panneau_search');
        }
    }

    private static function url(): ?string
    {
        $url = static::URL === null ? false : getenv(static::URL);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(string $title, array $attributes = []): SearchEntry
    {
        return SearchEntry::query()->create(['title' => $title] + $attributes);
    }

    /**
     * The titles found by a search, in the order of their creation.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function search(string $search, array $fields = ['title', 'body']): array
    {
        $page = $this->entries->query(new ResourceQuery(search: $search, searchFields: $fields));

        return array_values(collect($page->items())->sortBy('id')->pluck('title')->all());
    }

    public function test_every_word_is_searched_in_any_field_in_any_order(): void
    {
        $this->entry('A dog in the park', ['body' => 'It is black and fast']);
        $this->entry('The black dog');
        $this->entry('A black cat', ['body' => 'Not a d-o-g']);
        $this->entry('A dog', ['body' => null]);

        $this->assertSame(['A dog in the park', 'The black dog'], $this->search('black dog'));
        $this->assertSame(['A dog in the park', 'The black dog'], $this->search('dog black'));
        $this->assertSame(['A dog in the park', 'The black dog'], $this->search("  dog \t black\u{a0}"));
        $this->assertSame(['A dog in the park', 'The black dog', 'A dog'], $this->search('dog'));
        // The words, not the phrase
        $this->assertSame(['A dog in the park'], $this->search('park dog fast'));
        // Parts of words
        $this->assertSame(['A dog in the park', 'The black dog'], $this->search('bla do'));
    }

    public function test_an_item_without_one_of_the_words_is_not_found(): void
    {
        $this->entry('A dog in the park', ['body' => 'It is black and fast']);

        $this->assertSame([], $this->search('black dog slow'));
        $this->assertSame([], $this->search('cat'));
        // Only in the fields searched
        $this->assertSame([], $this->search('black dog', ['title']));
    }

    public function test_accents_are_ignored_both_ways(): void
    {
        $this->entry('Étude sur le français', ['body' => 'Ça a été publié à Noël']);
        $this->entry('Etude sur le francais', ['body' => 'Ca a ete publie a Noel']);
        $this->entry('Autre chose');
        $both = ['Étude sur le français', 'Etude sur le francais'];

        $this->assertSame($both, $this->search('etude'));
        $this->assertSame($both, $this->search('étude'));
        $this->assertSame($both, $this->search('Étude'));
        $this->assertSame($both, $this->search('ÉTUDE FRANÇAIS'));
        $this->assertSame($both, $this->search('ètüdê'));
        $this->assertSame($both, $this->search('noel francais publie'));
        $this->assertSame($both, $this->search('noël français publié'));
    }

    public function test_case_is_ignored(): void
    {
        $this->entry('Summer DAYS', ['body' => 'long and Warm']);

        $this->assertSame(['Summer DAYS'], $this->search('summer'));
        $this->assertSame(['Summer DAYS'], $this->search('SUMMER days'));
        $this->assertSame(['Summer DAYS'], $this->search('WARM sUmMeR'));
    }

    public function test_every_accented_letter_is_read_as_its_base_letter(): void
    {
        $accented = [];
        foreach (SearchTerms::letters() as $letter => $base) {
            $accented[$base] = ($accented[$base] ?? '').$letter;
        }
        $this->assertCount(19, $accented);
        foreach ($accented as $base => $letters) {
            $plain = str_repeat((string) $base, mb_strlen($letters));
            $this->entry('<'.$letters.'>');
            $this->entry('<'.$plain.'>');
        }

        foreach ($accented as $base => $letters) {
            $plain = str_repeat((string) $base, mb_strlen($letters));
            $expected = ['<'.$letters.'>', '<'.$plain.'>'];
            $this->assertSame($expected, $this->search('<'.$plain.'>', ['title']), 'The letters read as "'.$base.'"');
            $this->assertSame($expected, $this->search('<'.$letters.'>', ['title']), 'The letters read as "'.$base.'"');
            $this->assertSame($expected, $this->search('<'.mb_strtoupper($plain).'>', ['title']), 'The letters read as "'.$base.'"');
        }
    }

    public function test_wildcards_and_the_escape_character_are_literal(): void
    {
        $this->entry('100% sure');
        $this->entry('100 ways');
        $this->entry('snake_case');
        $this->entry('snakeXcase');
        $this->entry('Wow! 50%');
        $this->entry('Wow 50');

        $this->assertSame(['100% sure'], $this->search('100%'));
        $this->assertSame(['100% sure', 'Wow! 50%'], $this->search('%'));
        $this->assertSame(['snake_case'], $this->search('snake_case'));
        $this->assertSame(['snake_case'], $this->search('_'));
        $this->assertSame(['Wow! 50%'], $this->search('wow!'));
        $this->assertSame(['Wow! 50%'], $this->search('! %'));
        $this->assertSame([], $this->search('!%'));
    }

    public function test_a_json_path_is_searched_like_a_column(): void
    {
        $this->entry('First', ['name' => ['fr' => 'Étude du Chien noir', 'en' => 'Study of the black dog']]);
        $this->entry('Second', ['name' => ['fr' => 'Le chat', 'en' => 'The cat']]);
        $this->entry('Third', ['name' => null]);

        $this->assertSame(['First'], $this->search('etude', ['name->fr']));
        $this->assertSame(['First'], $this->search('NOIR chien', ['name->fr']));
        $this->assertSame([], $this->search('study', ['name->fr']));
        $this->assertSame(['First'], $this->search('study étude', ['name->fr', 'name->en']));
        $this->assertSame(['First', 'Second'], $this->search('the', ['title', 'name->en']));
        $this->assertSame(['Third'], $this->search('THIRD', ['title', 'name->en']));
    }

    public function test_the_words_over_the_most_searched_are_ignored(): void
    {
        $this->assertSame(8, SearchTerms::MAX_WORDS);
        $this->entry('one two three four five six seven eight');

        $this->assertSame(['one two three four five six seven eight'], $this->search('one two three four five six seven eight'));
        $this->assertSame([], $this->search('one two three four five six seven missing'));
        // The ninth word isn't searched
        $this->assertSame(['one two three four five six seven eight'], $this->search('one two three four five six seven eight missing'));
        // A word repeated counts once
        $this->assertSame([], $this->search('one one one one one one one one missing'));
    }

    public function test_the_count_of_a_search_is_the_one_of_its_items(): void
    {
        $this->entry('Étude', ['body' => 'noir']);
        $this->entry('Etude', ['body' => 'blanc']);

        $this->assertSame(1, $this->entries->query(new ResourceQuery(search: 'etude noir', searchFields: ['title', 'body']))->total());
        $this->assertSame(2, $this->entries->query(new ResourceQuery(search: 'ÉTUDE', searchFields: ['title', 'body']))->total());
    }
}
