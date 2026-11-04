<?php

namespace Panneau\Tests\Unit\Fields;

use Panneau\Fields\Tel;
use Panneau\Tests\TestCase;

class TelCountryTest extends TestCase
{
    public function test_phone_numbers_take_the_country_of_the_config_without_their_own(): void
    {
        $this->assertSame(['name' => 'phone', 'type' => 'tel'], Tel::make('phone')->toDefinition());

        config(['panneau.phones.country' => 'FR']);
        $this->assertSame(['name' => 'phone', 'type' => 'tel', 'settings' => ['country' => 'FR']], Tel::make('phone')->toDefinition());
        $this->assertSame(
            ['name' => 'phone', 'type' => 'tel', 'settings' => ['country' => 'CA']],
            Tel::make('phone')->country('CA')->toDefinition(),
        );
    }

    public function test_phone_numbers_can_have_an_extension(): void
    {
        $this->assertSame(
            ['name' => 'phone', 'type' => 'tel', 'settings' => ['extension' => true]],
            Tel::make('phone')->extension()->toDefinition(),
        );
        $this->assertTrue(Tel::make('phone')->extension()->allowsExtension());
        $this->assertFalse(Tel::make('phone')->extension(false)->allowsExtension());
        $this->assertFalse(Tel::make('phone')->allowsExtension());
    }
}
