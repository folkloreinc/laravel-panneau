<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Panneau\Tests\TestCase;

class ConvertPhonesCommandTest extends TestCase
{
    /**
     * An in-memory database, so that the table of the test goes away with it.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
        config(['panneau.phones' => [
            'country' => 'CA',
            'columns' => ['contacts.phone', 'contacts.data:contact.phone'],
        ]]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function contact(?string $phone, ?array $data = null): int
    {
        return DB::table('contacts')->insertGetId([
            'phone' => $phone,
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
        $row = (array) DB::table('contacts')->find($id);
        $row['data'] = json_decode((string) $row['data'], true);

        return $row;
    }

    public function test_numbers_of_columns_and_json_keys_become_e164_and_the_others_stay_as_typed(): void
    {
        $typed = $this->contact('514 555-0100', ['contact' => ['name' => 'Ada', 'phone' => '(438) 555-0101'], 'notes' => 'Rappeler']);
        $name = $this->contact('Tremblay', ['contact' => ['phone' => '+33 6 12 34 56 78']]);
        $empty = $this->contact(null);

        $this->artisan('panneau:convert-phones', ['-v' => true])
            ->expectsOutputToContain('contacts.phone: 1 converted')
            ->expectsOutputToContain('contacts.data:contact.phone: 2 converted')
            ->expectsOutputToContain('aaaaaaaa')
            ->doesntExpectOutputToContain('Tremblay')
            ->assertSuccessful();

        $row = $this->row($typed);
        $this->assertSame('+15145550100', $row['phone']);
        $this->assertSame(['contact' => ['name' => 'Ada', 'phone' => '+14385550101'], 'notes' => 'Rappeler'], $row['data']);
        $this->assertSame('2026-01-01 00:00:00', $row['updated_at']);
        $this->assertSame('Tremblay', $this->row($name)['phone']);
        $this->assertSame('+33612345678', $this->row($name)['data']['contact']['phone']);
        $this->assertNull($this->row($empty)['phone']);

        // A second run has nothing left to do
        $this->artisan('panneau:convert-phones')
            ->expectsOutputToContain('contacts.phone: 0 converted, 1 already in E.164')
            ->expectsOutputToContain('contacts.data:contact.phone: 0 converted, 2 already in E.164')
            ->assertSuccessful();
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $id = $this->contact('514 555-0100');

        $this->artisan('panneau:convert-phones', ['--dry-run' => true])
            ->expectsOutputToContain('contacts.phone: 1 to convert')
            ->assertSuccessful();

        $this->assertSame('514 555-0100', $this->row($id)['phone']);
    }

    public function test_columns_and_country_can_be_given(): void
    {
        $id = $this->contact('06 12 34 56 78');

        $this->artisan('panneau:convert-phones', ['columns' => ['contacts.phone'], '--country' => 'FR'])
            ->expectsOutputToContain('contacts.phone: 1 converted')
            ->doesntExpectOutputToContain('contacts.data')
            ->assertSuccessful();

        $this->assertSame('+33612345678', $this->row($id)['phone']);
    }

    public function test_a_country_is_needed(): void
    {
        config(['panneau.phones.country' => null]);

        $this->artisan('panneau:convert-phones')->assertFailed();
    }
}
