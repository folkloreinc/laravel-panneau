<?php

namespace Panneau\Tests\Unit\Fields;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Panneau\Fields\Address;
use Panneau\Fields\Field;
use Panneau\Fields\Group;
use Panneau\Fields\Text;
use Panneau\Forms\Form;
use Panneau\Forms\ValidationContext;
use Panneau\Tests\TestCase;

class AddressTest extends TestCase
{
    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $fields, array $input): array
    {
        return Form::validate($this->app['validator'], $fields, $input, new ValidationContext(locales: ['fr', 'en']));
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    private function errors(array $fields, array $input): array
    {
        try {
            $this->validate($fields, $input);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }

    public function test_the_definition(): void
    {
        $this->assertSame(
            ['name' => 'address', 'type' => 'address', 'settings' => ['country' => 'CA', 'countries' => ['CA', 'US'], 'preferred' => ['US'], 'required' => ['street', 'postalCode'], 'point' => true]],
            Address::make('address')->country('CA')->countries(['CA', 'US'])->preferred(['US'])->requiredParts(['street', 'postalCode', 'street'])->point()->toDefinition(),
        );
        $this->assertSame(Address::DEFAULT_REQUIRED_PARTS, Address::make('address')->getRequiredParts());
    }

    public function test_parts_and_countries_are_checked_when_set(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Address::make('address')->requiredParts(['street', 'zip']);
    }

    public function test_a_country_is_a_code_in_capitals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Address::make('address')->country('ca');
    }

    public function test_an_address_without_parts_is_null_and_isnt_checked(): void
    {
        $fields = [Address::make('address')];

        $this->assertSame(['address' => null], $this->validate($fields, ['address' => ['street' => ' ', 'city' => null, 'unit' => '']]));
        // A country alone is no address, as clients count it: no errors of its parts, and null
        $this->assertSame([], $this->errors($fields, ['address' => ['country' => 'ZZ', 'street' => '']]));
        $this->assertSame(['address' => null], $this->validate($fields, ['address' => ['country' => 'CA']]));
        $this->assertSame(['address' => null], $this->validate([Address::make('address')->country('CA')], ['address' => ['country' => 'US', 'unit' => ' ']]));
        $this->assertSame(['address' => null], $this->validate($fields, ['address' => null]));
        // The field's own required() requires an address
        $this->assertSame(['address'], array_keys($this->errors([Address::make('address')->required()], ['address' => ['street' => '']])));
        $this->assertSame(['address'], array_keys($this->errors([Address::make('address')->required()], [])));
        $this->assertSame(['address'], array_keys($this->errors([Address::make('address')->required()->country('CA')], ['address' => ['country' => 'CA']])));
    }

    public function test_the_required_parts_of_a_filled_address(): void
    {
        $this->assertSame(
            ['address.street', 'address.city', 'address.country'],
            array_keys($this->errors([Address::make('address')], ['address' => ['unit' => 'App. 5']])),
        );
        // Each of the parts other than the country fills it
        foreach (Address::FILLING_PARTS as $part) {
            $this->assertNotSame([], $this->errors([Address::make('address')->requiredParts(['country'])], ['address' => [$part => 'A1A 1A1']]), $part);
        }
        $this->assertSame(
            ['address.postalCode'],
            array_keys($this->errors([Address::make('address')->requiredParts(['postalCode'])], ['address' => ['city' => 'Laval']])),
        );
    }

    public function test_parts_are_strings_and_the_country_one_of_the_field(): void
    {
        $fields = [Address::make('address')->countries(['CA', 'US'])];

        $this->assertSame(
            ['address.street', 'address.city', 'address.country'],
            array_keys($this->errors($fields, ['address' => ['street' => ['1234'], 'city' => str_repeat('a', 256), 'country' => 'FR']])),
        );
        $this->assertSame(
            ['address' => ['street' => '1 Main Street', 'city' => 'Albany', 'region' => 'NY', 'postalCode' => '12207', 'country' => 'US']],
            $this->validate($fields, ['address' => ['street' => '1 Main Street', 'city' => 'Albany', 'region' => 'New York', 'postalCode' => '12207', 'country' => 'us']]),
        );
    }

    public function test_the_postal_code_is_one_of_the_country_of_the_address(): void
    {
        $fields = [Address::make('address')->country('CA')];
        $address = ['street' => '1 Main Street', 'city' => 'Albany'];

        $this->assertSame(['address.postalCode'], array_keys($this->errors($fields, ['address' => $address + ['postalCode' => '12207']])));
        // The country of the address, not the one of the field
        $this->assertSame([], $this->errors($fields, ['address' => $address + ['postalCode' => '12207', 'country' => 'US']]));
        $this->assertSame(
            ['address' => $address + ['postalCode' => 'H2X 1Y4', 'country' => 'CA']],
            $this->validate($fields, ['address' => $address + ['postalCode' => 'h2x1y4']]),
        );
    }

    public function test_the_point(): void
    {
        $fields = [Address::make('address')->point()];
        $address = ['street' => '1 Main Street', 'city' => 'Albany', 'country' => 'US'];

        $this->assertSame(['address.point.lng'], array_keys($this->errors($fields, ['address' => $address + ['point' => ['lat' => 42.65]]])));
        $this->assertSame(
            ['address' => $address + ['point' => ['lat' => 42.65, 'lng' => -73.75]]],
            $this->validate($fields, ['address' => $address + ['point' => ['lat' => 42.65, 'lng' => -73.75]]]),
        );
        $this->assertSame(['address' => $address], $this->validate($fields, ['address' => $address + ['point' => null]]));
        // A point alone is kept, without its country nor errors of the parts
        $this->assertSame(
            ['address' => ['point' => ['lat' => 42.65, 'lng' => -73.75]]],
            $this->validate([Address::make('address')->point()->country('US')], ['address' => ['country' => 'US', 'point' => ['lat' => 42.65, 'lng' => -73.75]]]),
        );
        $this->assertSame(['address.point.lat'], array_keys($this->errors($fields, ['address' => ['point' => ['lat' => 91, 'lng' => 0]]])));
        // Not filled: the field's own required() refuses it
        $this->assertSame(['address'], array_keys($this->errors([Address::make('address')->point()->required()], ['address' => ['point' => ['lat' => 1, 'lng' => 2]]])));
    }

    public function test_localized_and_nested_addresses_are_checked_on_their_paths(): void
    {
        $this->assertSame(
            ['address.en.city', 'address.en.postalCode'],
            array_keys($this->errors([Address::make('address')->localized()->country('CA')], ['address' => [
                'fr' => ['street' => '1 rue Principale', 'city' => 'Laval', 'postalCode' => 'H7N 1A1'],
                'en' => ['street' => '1 Main Street', 'postalCode' => 'H7N'],
            ]])),
        );
        $this->assertSame(
            ['contact.address.street'],
            array_keys($this->errors([Group::make('contact')->fields([Text::make('name'), Address::make('address')])], ['contact' => ['address' => ['city' => 'Laval', 'country' => 'CA']]])),
        );
    }

    public function test_the_postal_code_rule_of_text_fields(): void
    {
        $fields = [
            Text::make('country'),
            Text::make('zip')->postalCode(field: 'country'),
            Text::make('code')->postalCode('CA', message: 'A Canadian postal code.'),
        ];

        $this->assertSame(['rule' => 'postalCode', 'value' => ['field' => 'country']], $fields[1]->toDefinition()['rules'][0]);
        $this->assertSame(['rule' => 'postalCode', 'value' => 'CA', 'message' => 'A Canadian postal code.'], $fields[2]->toDefinition()['rules'][0]);
        $errors = $this->errors($fields, ['country' => 'US', 'zip' => 'H2X 1Y4', 'code' => '12345']);
        $this->assertSame(['zip', 'code'], array_keys($errors));
        $this->assertSame(['A Canadian postal code.'], $errors['code']);
        $this->assertSame([], $this->errors($fields, ['country' => 'FR', 'zip' => '75001', 'code' => 'h2x1y4']));
        // In a group, the field of the same object
        $group = [Group::make('shipping')->fields([Text::make('country'), Text::make('zip')->postalCode(field: 'country')])];
        $this->assertSame(['shipping.zip'], array_keys($this->errors($group, ['shipping' => ['country' => 'CA', 'zip' => '12345']])));
    }
}
