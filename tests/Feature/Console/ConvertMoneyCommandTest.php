<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Panneau\Tests\TestCase;

class ConvertMoneyCommandTest extends TestCase
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

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->decimal('price', 10, 2)->nullable();
            // Added by the project's migration, for the AsMoney cast
            $table->bigInteger('price_cents')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
        config(['panneau.money' => [
            'currency' => 'CAD',
            'columns' => ['products.price:price_cents', 'products.data:offer.total'],
        ]]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function product(mixed $price, ?array $data = null, ?int $cents = null): int
    {
        return DB::table('products')->insertGetId([
            'price' => $price,
            'price_cents' => $cents,
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
        $row = (array) DB::table('products')->find($id);
        $row['data'] = json_decode((string) $row['data'], true);

        return $row;
    }

    public function test_amounts_in_major_units_become_minor_units(): void
    {
        $decimal = $this->product('12.50', ['offer' => ['label' => 'Promo', 'total' => 1.005], 'notes' => 'Kept']);
        $text = $this->product(null, ['offer' => ['total' => '19,99 $']]);
        $string = $this->product('3', ['offer' => ['total' => '7.5']]);
        $empty = $this->product(null);

        $this->artisan('panneau:convert-money', ['-v' => true])
            ->expectsOutputToContain('products.price:price_cents: 2 converted, 0 already in minor units, 2 without an amount, 0 kept')
            ->expectsOutputToContain('products.data:offer.total: 2 converted, 0 already in minor units, 1 without an amount, 1 kept')
            ->expectsOutputToContain('99,99 $')
            ->doesntExpectOutputToContain('19,99')
            ->assertSuccessful();

        $row = $this->row($decimal);
        $this->assertSame(1250, (int) $row['price_cents']);
        $this->assertSame(['offer' => ['label' => 'Promo', 'total' => ['amount' => 101, 'currency' => 'CAD']], 'notes' => 'Kept'], $row['data']);
        $this->assertSame('2026-01-01 00:00:00', $row['updated_at']);
        $this->assertSame(300, (int) $this->row($string)['price_cents']);
        $this->assertSame(['amount' => 750, 'currency' => 'CAD'], $this->row($string)['data']['offer']['total']);
        $this->assertSame('19,99 $', $this->row($text)['data']['offer']['total']);
        $this->assertNull($this->row($empty)['price_cents']);

        // A second run has nothing left to do
        $this->artisan('panneau:convert-money')
            ->expectsOutputToContain('products.price:price_cents: 0 converted, 2 already in minor units')
            ->expectsOutputToContain('products.data:offer.total: 0 converted, 2 already in minor units')
            ->assertSuccessful();
    }

    public function test_a_filled_target_is_never_overwritten(): void
    {
        $id = $this->product('12.50', cents: 999);

        $this->artisan('panneau:convert-money', ['columns' => ['products.price:price_cents']])
            ->expectsOutputToContain('products.price:price_cents: 0 converted, 1 already in minor units')
            ->assertSuccessful();

        $this->assertSame(999, (int) $this->row($id)['price_cents']);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $id = $this->product('12.50', ['offer' => ['total' => 3]]);

        $this->artisan('panneau:convert-money', ['--dry-run' => true])
            ->expectsOutputToContain('products.price:price_cents: 1 to convert')
            ->expectsOutputToContain('products.data:offer.total: 1 to convert')
            ->assertSuccessful();

        $this->assertNull($this->row($id)['price_cents']);
        $this->assertSame(3, $this->row($id)['data']['offer']['total']);
    }

    public function test_the_digits_of_the_currency_apply(): void
    {
        $id = $this->product('1250.4', ['offer' => ['total' => 1.2345]]);

        $this->artisan('panneau:convert-money', ['columns' => ['products.price:price_cents'], '--currency' => 'JPY'])->assertSuccessful();
        $this->artisan('panneau:convert-money', ['columns' => ['products.data:offer.total'], '--currency' => 'KWD'])->assertSuccessful();

        $this->assertSame(1250, (int) $this->row($id)['price_cents']);
        $this->assertSame(['amount' => 1235, 'currency' => 'KWD'], $this->row($id)['data']['offer']['total']);
    }

    public function test_a_known_currency_is_needed(): void
    {
        config(['panneau.money.currency' => null]);
        $this->artisan('panneau:convert-money')->assertFailed();
        $this->artisan('panneau:convert-money', ['--currency' => 'cad'])->assertFailed();
    }

    public function test_targets_are_checked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->artisan('panneau:convert-money', ['columns' => ['products.price']]);
    }
}
