<?php

namespace Panneau\Tests\Unit\Rules;

use Panneau\Fields\Regions;
use Panneau\Rules\PostalCode;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PostalCodeTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null, string, bool}>
     */
    public static function codes(): array
    {
        return [
            'Canada' => ['H2X 1Y4', 'CA', 'H2X 1Y4', true],
            'Canada, lower case without space' => ['h2x1y4', 'CA', 'H2X 1Y4', true],
            'Canada, hyphen and spaces' => [' h2x-1y4 ', 'ca', 'H2X 1Y4', true],
            'Canada, a letter Canada Post does not use' => ['D2X 1Y4', 'CA', 'D2X 1Y4', false],
            'Canada, W first' => ['W2X 1Y4', 'CA', 'W2X 1Y4', false],
            'Canada, too short' => ['h2x 1y', 'CA', 'H2X 1Y', false],
            'Canada, a ZIP code' => ['12345', 'CA', '12345', false],
            'United States' => ['12345', 'US', '12345', true],
            'United States, ZIP+4' => ['12345-6789', 'US', '12345-6789', true],
            'United States, nine digits' => ['123456789', 'US', '12345-6789', true],
            'United States, a space' => ['12345 6789', 'US', '12345-6789', true],
            'United States, four digits' => ['1234', 'US', '1234', false],
            'United States, letters' => ['h2x 1y4', 'US', 'H2X 1Y4', false],
            'France' => [' 75001 ', 'FR', '75001', true],
            'United Kingdom, collapsed' => ['sw1a   1aa', 'GB', 'SW1A 1AA', true],
            'no country' => ['anything  goes', null, 'ANYTHING GOES', true],
        ];
    }

    #[DataProvider('codes')]
    public function test_postal_codes_are_normalized_and_checked_by_country(string $code, ?string $country, string $normalized, bool $valid): void
    {
        $this->assertSame($normalized, PostalCode::normalize($code, $country));
        $this->assertSame($valid, PostalCode::isValid($code, $country));
    }

    public function test_the_rule_reads_the_country_of_another_field(): void
    {
        $failed = null;
        $rule = (new PostalCode(countryPath: 'shipping.country'))->setData(['shipping' => ['country' => 'us']]);
        $rule->validate('shipping.zip', 'H2X 1Y4', function (string $message) use (&$failed): void {
            $failed = $message;
        });
        $this->assertNotNull($failed);

        $failed = null;
        $rule->setData(['shipping' => ['country' => '']])->validate('shipping.zip', 'H2X 1Y4', function (string $message) use (&$failed): void {
            $failed = $message;
        });
        $this->assertNull($failed);
    }

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function regions(): array
    {
        return [
            'a code' => ['qc', 'CA', 'QC'],
            'English' => ['Quebec', 'CA', 'QC'],
            'French, with accents' => ['Québec', 'CA', 'QC'],
            'capitals' => ['QUÉBEC', 'CA', 'QC'],
            'an abbreviation' => ['Qué.', 'CA', 'QC'],
            'a French name with hyphens' => ['Île-du-Prince-Édouard', 'CA', 'PE'],
            'another spelling' => ['Colombie Britannique', 'CA', 'BC'],
            'a state' => ['new york', 'US', 'NY'],
            'a state in French' => ['Caroline du Nord', 'US', 'NC'],
            'a region of another country' => ['Quebec', 'US', null],
            'a country whose regions are not known' => ['Bavaria', 'DE', null],
            'unknown' => ['Atlantis', 'CA', null],
        ];
    }

    #[DataProvider('regions')]
    public function test_regions_of_canada_and_the_united_states_have_codes(string $region, string $country, ?string $code): void
    {
        $this->assertSame($code, Regions::code($region, $country));
    }
}
