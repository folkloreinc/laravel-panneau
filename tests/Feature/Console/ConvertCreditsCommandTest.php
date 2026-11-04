<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Panneau\Tests\TestCase;

class ConvertCreditsCommandTest extends TestCase
{
    /**
     * An in-memory database, so that the table of the test goes away with it.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.locale', 'fr');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('films', function (Blueprint $table): void {
            $table->id();
            $table->json('credits')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
        config(['panneau.credits.columns' => ['films.credits', 'films.data:production.credits']]);
    }

    /**
     * @param  list<mixed>|null  $credits
     * @param  array<string, mixed>|null  $data
     */
    private function film(?array $credits, ?array $data = null): int
    {
        return DB::table('films')->insertGetId([
            'credits' => $credits !== null ? json_encode($credits) : null,
            'data' => $data !== null ? json_encode($data) : null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id): array
    {
        $row = (array) DB::table('films')->find($id);
        $row['credits'] = json_decode((string) $row['credits'], true);
        $row['data'] = json_decode((string) $row['data']);

        return $row;
    }

    public function test_credits_of_people_and_roles_kept_as_references(): void
    {
        $id = $this->film([
            ['person' => 'people://12', 'roles' => ['taxonomies://5', 6], 'featured' => true, 'description' => ['fr' => 'Une note', 'en' => 'A note']],
            ['person' => ['id' => 13, 'label' => 'Grace'], 'role' => ['id' => '7', 'label' => 'Music'], 'featured' => false],
        ]);

        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits']])
            ->expectsOutputToContain('films.credits: 1 converted')
            ->assertSuccessful();

        $this->assertSame([
            ['person' => ['id' => '12'], 'roles' => [['id' => '5'], ['id' => '6']], 'featured' => true, 'description' => ['fr' => 'Une note', 'en' => 'A note']],
            ['person' => ['id' => '13'], 'roles' => [['id' => '7']]],
        ], $this->row($id)['credits']);
        $this->assertSame('2026-01-01 00:00:00', $this->row($id)['updated_at']);
    }

    public function test_credits_grouped_by_role_become_a_credit_per_person(): void
    {
        $id = $this->film([
            ['title' => ['fr' => 'Réalisation', 'en' => 'Direction'], 'people' => ['person://3', 'person://7']],
            ['role' => ['fr' => 'Musique', 'en' => 'Music'], 'people' => [['name' => 'Jane Doe'], ['firstName' => 'John', 'lastName' => 'Roe']]],
        ]);

        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits']])->assertSuccessful();

        $direction = [['name' => ['fr' => 'Réalisation', 'en' => 'Direction']]];
        $music = [['name' => ['fr' => 'Musique', 'en' => 'Music']]];
        $this->assertSame([
            ['person' => ['id' => '3'], 'roles' => $direction],
            ['person' => ['id' => '7'], 'roles' => $direction],
            ['person' => ['name' => 'Jane Doe'], 'roles' => $music],
            ['person' => ['name' => 'John Roe'], 'roles' => $music],
        ], $this->row($id)['credits']);
    }

    public function test_a_single_role_key_becomes_a_name_in_the_locale(): void
    {
        $id = $this->film([['person' => 4, 'role' => 'director'], ['person' => 5, 'role' => '']]);
        $other = $this->film([['person' => 4, 'roles' => ['editor', 'music']]]);

        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits']])->assertSuccessful();

        // The locale of the app, or --locale
        $this->assertSame([
            ['person' => ['id' => '4'], 'roles' => [['name' => ['fr' => 'director']]]],
            ['person' => ['id' => '5'], 'roles' => []],
        ], $this->row($id)['credits']);

        DB::table('films')->where('id', $other)->update(['credits' => json_encode([['person' => 4, 'role' => 'director']])]);
        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits'], '--locale' => 'en'])->assertSuccessful();
        $this->assertSame([['person' => ['id' => '4'], 'roles' => [['name' => ['en' => 'director']]]]], $this->row($other)['credits']);
    }

    public function test_the_person_key_is_found_or_given(): void
    {
        // The only key that holds a reference
        $found = $this->film([['member' => 'people://8', 'role' => 'people://99', 'note' => 'Kept']]);
        // Ambiguous: two keys hold references, and no name
        $ambiguous = $this->film([['member' => 'people://8', 'director' => 'people://9']]);
        // A name at the top of the credit
        $named = $this->film([['name' => ' Jane Doe ', 'role' => 'Music']]);

        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits'], '-v' => true])
            ->expectsOutputToContain('films.credits: 2 converted, 0 already converted, 0 without credits, 1 kept as they were')
            ->expectsOutputToContain('a credit without a person')
            ->assertSuccessful();

        $this->assertSame([['person' => ['id' => '8'], 'roles' => [['id' => '99']], 'note' => 'Kept']], $this->row($found)['credits']);
        $this->assertSame([['member' => 'people://8', 'director' => 'people://9']], $this->row($ambiguous)['credits']);
        $this->assertSame([['person' => ['name' => 'Jane Doe'], 'roles' => [['name' => ['fr' => 'Music']]]]], $this->row($named)['credits']);

        // --person names the key of the person
        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits'], '--person' => ['director']])
            ->expectsOutputToContain('films.credits: 1 converted, 2 already converted')
            ->assertSuccessful();
        $this->assertSame([['person' => ['id' => '9'], 'roles' => [], 'member' => 'people://8']], $this->row($ambiguous)['credits']);
    }

    public function test_credits_in_a_key_of_a_json_column_and_the_other_keys_stay(): void
    {
        $id = $this->film(null, ['production' => ['year' => 2026, 'credits' => [['person' => 'people://12', 'role' => 'people://3']]], 'settings' => (object) []]);

        $this->artisan('panneau:convert-credits')
            ->expectsOutputToContain('films.credits: 0 converted, 0 already converted, 1 without credits')
            ->expectsOutputToContain('films.data:production.credits: 1 converted')
            ->assertSuccessful();

        $data = $this->row($id)['data'];
        $this->assertSame(2026, $data->production->year);
        $this->assertEquals([(object) ['person' => (object) ['id' => '12'], 'roles' => [(object) ['id' => '3']]]], $data->production->credits);
        // An empty object stays an object
        $this->assertEquals((object) [], $data->settings);
    }

    public function test_converted_credits_stay_as_they_are(): void
    {
        $converted = [
            ['person' => ['id' => '12'], 'roles' => [['id' => '3']], 'featured' => true, 'description' => ['fr' => 'Une note']],
            ['person' => ['name' => 'Jane Doe'], 'roles' => [['name' => ['fr' => 'Musique', 'en' => 'Music']]]],
        ];
        $id = $this->film($converted);
        $legacy = $this->film([['person' => 'people://12', 'roles' => ['taxonomies://5']]]);

        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits']])
            ->expectsOutputToContain('films.credits: 1 converted, 1 already converted')
            ->assertSuccessful();
        $this->assertSame($converted, $this->row($id)['credits']);

        // A second run has nothing left to do
        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits']])
            ->expectsOutputToContain('films.credits: 0 converted, 2 already converted')
            ->assertSuccessful();
        $this->assertSame([['person' => ['id' => '12'], 'roles' => [['id' => '5']]]], $this->row($legacy)['credits']);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $credits = [['person' => 'people://12', 'roles' => ['taxonomies://5']]];
        $id = $this->film($credits);

        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits'], '--dry-run' => true])
            ->expectsOutputToContain('films.credits: 1 to convert')
            ->assertSuccessful();

        $this->assertSame($credits, $this->row($id)['credits']);
    }

    public function test_values_it_cant_read_are_listed_without_their_content(): void
    {
        $id = $this->film([['person' => 'Jane Doe', 'role' => 'Music']]);
        $this->film(['not' => 'a list']);

        $this->artisan('panneau:convert-credits', ['columns' => ['films.credits'], '-v' => true])
            ->expectsOutputToContain('2 kept as they were')
            ->expectsOutputToContain('a person that is neither a reference nor a name')
            ->expectsOutputToContain('not a list of credits')
            ->doesntExpectOutputToContain('Jane Doe')
            ->assertSuccessful();

        $this->assertSame([['person' => 'Jane Doe', 'role' => 'Music']], $this->row($id)['credits']);
    }

    public function test_without_columns_nothing_happens(): void
    {
        config(['panneau.credits.columns' => []]);

        $this->artisan('panneau:convert-credits')
            ->expectsOutputToContain('No columns to convert')
            ->assertSuccessful();
    }
}
