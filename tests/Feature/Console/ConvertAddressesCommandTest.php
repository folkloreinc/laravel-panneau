<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

class ConvertAddressesCommandTest extends TestCase
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

        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->json('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->json('location')->nullable();
            $table->timestamps();
        });
        config(['panneau.addresses' => [
            'country' => 'CA',
            'columns' => [
                // A new column, from the parts
                'venues.location' => ['street' => 'address', 'unit' => 'address2', 'city' => 'city:fr', 'region' => 'province', 'postalCode' => 'postal_code', 'country' => 'country'],
            ],
        ]]);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function venue(array $parts): int
    {
        return DB::table('venues')->insertGetId([
            ...$parts,
            'city' => isset($parts['city']) ? json_encode(['fr' => $parts['city'], 'en' => 'Elsewhere']) : null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function location(int $id): ?array
    {
        $location = DB::table('venues')->where('id', $id)->value('location');

        return is_string($location) ? json_decode($location, true) : null;
    }

    #[RequiresPhpExtension('intl')]
    public function test_the_parts_become_an_address_and_unknown_values_stay_as_typed(): void
    {
        $montreal = $this->venue(['address' => ' 1234 Rue Saint-Denis ', 'address2' => '', 'city' => 'Montréal', 'province' => 'Québec', 'postal_code' => 'h2x1y4', 'country' => 'Canada']);
        $newYork = $this->venue(['address' => '350 Fifth Avenue', 'city' => 'New York', 'province' => 'new york', 'postal_code' => '101180110', 'country' => 'États-Unis']);
        $paris = $this->venue(['address' => '1 rue de Rivoli', 'city' => 'Paris', 'postal_code' => '75001', 'country' => 'fr']);
        $laval = $this->venue(['address' => '1 Boulevard', 'city' => 'Laval', 'province' => 'Kebek', 'postal_code' => 'H7N', 'country' => 'CA']);
        $berlin = $this->venue(['address' => 'Unter den Linden 1', 'city' => 'Berlin', 'country' => 'Deutschlandd']);
        // A country alone is no address
        $empty = $this->venue(['address' => '', 'country' => 'Canada']);

        $this->artisan('panneau:convert-addresses', ['-v' => true])
            ->expectsOutputToContain('venues.location: 5 converted, 0 already an address, 1 without an address, 2 with parts kept as typed')
            // By their shape only
            ->expectsOutputToContain('aaaaaaaaaaaa')
            ->expectsOutputToContain('a9a')
            ->doesntExpectOutputToContain('Deutschlandd')
            ->doesntExpectOutputToContain('Kebek')
            ->assertSuccessful();

        $this->assertSame(['street' => '1234 Rue Saint-Denis', 'city' => 'Montréal', 'region' => 'QC', 'postalCode' => 'H2X 1Y4', 'country' => 'CA'], $this->location($montreal));
        $this->assertSame(['street' => '350 Fifth Avenue', 'city' => 'New York', 'region' => 'NY', 'postalCode' => '10118-0110', 'country' => 'US'], $this->location($newYork));
        $this->assertSame(['street' => '1 rue de Rivoli', 'city' => 'Paris', 'postalCode' => '75001', 'country' => 'FR'], $this->location($paris));
        $this->assertSame(['street' => '1 Boulevard', 'city' => 'Laval', 'region' => 'Kebek', 'postalCode' => 'H7N', 'country' => 'CA'], $this->location($laval));
        $this->assertSame(['street' => 'Unter den Linden 1', 'city' => 'Berlin', 'country' => 'Deutschlandd'], $this->location($berlin));
        $this->assertNull($this->location($empty));
        // The parts and the dates are left alone
        $row = DB::table('venues')->find($montreal);
        $this->assertSame('h2x1y4', $row->postal_code);
        $this->assertSame('2026-01-01 00:00:00', $row->updated_at);

        // A second run has nothing left to do
        $this->artisan('panneau:convert-addresses')
            ->expectsOutputToContain('venues.location: 0 converted, 5 already an address, 1 without an address')
            ->assertSuccessful();
    }

    public function test_the_country_of_the_config_and_a_target_that_is_also_a_source(): void
    {
        config(['panneau.addresses.columns' => ['venues.address' => ['street' => 'address', 'city' => 'city:fr', 'region' => 'province']]]);
        $id = $this->venue(['address' => '1 Main Street', 'city' => 'Albany', 'province' => 'NY']);

        $this->artisan('panneau:convert-addresses', ['--country' => 'US'])
            ->expectsOutputToContain('venues.address: 1 converted')
            ->assertSuccessful();

        $this->assertSame(
            ['street' => '1 Main Street', 'city' => 'Albany', 'region' => 'NY', 'country' => 'US'],
            json_decode((string) DB::table('venues')->where('id', $id)->value('address'), true),
        );
        $this->artisan('panneau:convert-addresses')
            ->expectsOutputToContain('venues.address: 0 converted, 1 already an address')
            ->assertSuccessful();
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $id = $this->venue(['address' => '1 Main Street', 'city' => 'Laval', 'country' => 'CA']);

        $this->artisan('panneau:convert-addresses', ['--dry-run' => true])
            ->expectsOutputToContain('venues.location: 1 to convert')
            ->assertSuccessful();

        $this->assertNull($this->location($id));
    }

    public function test_targets_and_parts_are_checked(): void
    {
        $this->artisan('panneau:convert-addresses', ['targets' => ['venues.other']])->assertFailed();

        config(['panneau.addresses.columns' => ['venues.location' => ['zip' => 'postal_code']]]);
        $this->expectExceptionMessage('"zip" of venues.location is not a part of an address');
        $this->artisan('panneau:convert-addresses');
    }

    public function test_without_columns(): void
    {
        config(['panneau.addresses.columns' => []]);

        $this->artisan('panneau:convert-addresses')
            ->expectsOutputToContain('No addresses to convert')
            ->assertSuccessful();
    }
}
