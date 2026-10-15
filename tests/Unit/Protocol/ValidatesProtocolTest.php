<?php

namespace Panneau\Tests\Unit\Protocol;

use Panneau\Tests\Concerns\ValidatesProtocol;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/**
 * Makes sure the schema checks used by the other tests do fail.
 */
class ValidatesProtocolTest extends TestCase
{
    use ValidatesProtocol;

    /**
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            'protocol' => 1,
            'name' => 'Admin',
            'locale' => 'en',
            'locales' => ['en'],
            'api' => ['baseUrl' => '/panneau/api'],
            'resources' => [[
                'id' => 'pages',
                'label' => ['singular' => 'page', 'plural' => 'pages'],
                'fields' => [['name' => 'title', 'type' => 'text']],
                'form' => ['layout' => [['type' => 'field', 'name' => 'title']]],
            ]],
        ];
    }

    public function test_a_valid_definition_passes(): void
    {
        $this->assertValidDefinition($this->definition());
    }

    public function test_a_schema_error_fails(): void
    {
        $definition = $this->definition();
        $definition['protocol'] = 2;

        $this->expectException(AssertionFailedError::class);
        $this->assertValidDefinition($definition);
    }

    public function test_a_missing_property_fails(): void
    {
        $definition = $this->definition();
        unset($definition['resources'][0]['fields'][0]['type']);

        $this->expectException(AssertionFailedError::class);
        $this->assertValidDefinition($definition);
    }

    public function test_an_invalid_reference_fails(): void
    {
        $definition = $this->definition();
        $definition['resources'][0]['form']['layout'][0]['name'] = 'body';

        $this->expectException(AssertionFailedError::class);
        $this->assertValidDefinition($definition);
    }

    public function test_components_of_the_openapi_document(): void
    {
        $this->assertMatchesComponent(['id' => '1', 'version' => null, 'abilities' => ['update' => true], 'title' => 'A'], 'Item');

        $this->expectException(AssertionFailedError::class);
        $this->assertMatchesComponent(['id' => 1, 'version' => null, 'abilities' => []], 'Item');
    }
}
