<?php

namespace Panneau\Tests\Unit\Fields;

use Panneau\Fields\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string, string|null}>
     */
    public static function numbers(): array
    {
        return [
            'Canada, digits' => ['5145550199', 'CA', '+15145550199'],
            'Canada, dashes' => ['514-555-0199', 'CA', '+15145550199'],
            'Canada, parentheses' => ['(514) 555-0199', 'CA', '+15145550199'],
            'Canada, dots' => ['514.555.0199', 'CA', '+15145550199'],
            'Canada, with 1' => ['1-514-555-0199', 'CA', '+15145550199'],
            'Canada, exit prefix' => ['011 33 6 12 34 56 78', 'CA', '+33612345678'],
            'United States' => ['(212) 555-0123', 'US', '+12125550123'],
            'France' => ['06 12 34 56 78', 'FR', '+33612345678'],
            'France, dots' => ['01.23.45.67.89', 'FR', '+33123456789'],
            'France, exit prefix' => ['0033 6 12 34 56 78', 'FR', '+33612345678'],
            'France, trunk in parentheses' => ['+33 (0)6 12 34 56 78', 'CA', '+33612345678'],
            'Belgium, mobile' => ['0470 12 34 56', 'BE', '+32470123456'],
            'Belgium, landline' => ['02 123 45 67', 'BE', '+3221234567'],
            'Switzerland' => ['078 123 45 67', 'CH', '+41781234567'],
            'Switzerland, slash' => ['078/123 45 67', 'CH', '+41781234567'],
            'E.164' => ['+15145550199', 'FR', '+15145550199'],
            'international, another country' => ['+44 20 7946 0958', 'CA', '+442079460958'],
            'national, a country without a plan' => ['020 7946 0958', 'GB', null],
            'Canada from France' => ['514 555-0199', 'FR', null],
            'a digit too few' => ['514-555-019', 'CA', null],
            'a digit too few after +1' => ['+1514555019', 'CA', null],
            'a digit too many' => ['514-555-01999', 'CA', null],
            'area code from 0' => ['014-555-0199', 'CA', null],
            'seven digits' => ['555-0199', 'CA', null],
            'an extension' => ['514-555-0199 ext:1234', 'CA', null],
            'a name' => ['Tremblay', 'CA', null],
            'empty' => ['  ', 'CA', null],
            'null' => [null, 'CA', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_become_e164_when_they_read_as_one(?string $typed, string $country, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($typed, $country));
    }

    public function test_numbers_show_in_the_format_of_the_country_or_with_their_calling_code(): void
    {
        $this->assertSame('514 555-0199', PhoneNumber::format('+15145550199', 'CA'));
        $this->assertSame('514 555-0199', PhoneNumber::format('+15145550199', 'US'));
        $this->assertSame('+1 514 555-0199', PhoneNumber::format('+15145550199', 'FR'));
        $this->assertSame('06 12 34 56 78', PhoneNumber::format('+33612345678', 'FR'));
        $this->assertSame('+33 6 12 34 56 78', PhoneNumber::format('+33612345678', 'CA'));
        $this->assertSame('078 123 45 67', PhoneNumber::format('+41781234567', 'CH'));
        $this->assertSame('0470123456', PhoneNumber::format('+32470123456', 'BE'));
        $this->assertSame('+442079460958', PhoneNumber::format('+442079460958', 'CA'));
        $this->assertSame('514-555-019', PhoneNumber::format('514-555-019', 'CA'));
        $this->assertNull(PhoneNumber::format(null, 'CA'));
    }

    public function test_the_national_number_of_a_number_of_the_country(): void
    {
        $this->assertSame('5145550199', PhoneNumber::nationalNumber('+15145550199', 'CA'));
        $this->assertSame('612345678', PhoneNumber::nationalNumber('+33612345678', 'FR'));
        $this->assertNull(PhoneNumber::nationalNumber('+33612345678', 'CA'));
        $this->assertNull(PhoneNumber::nationalNumber('514 555-0199', 'CA'));
    }
}
