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

    public function test_media_libraries_are_linted(): void
    {
        $definition = $this->definition();
        $library = [
            'id' => 'media',
            'kind' => 'media',
            'label' => ['singular' => 'media', 'plural' => 'media'],
            'fields' => [['name' => 'file', 'type' => 'file']],
        ];
        $definition['resources'][] = $library;
        $definition['resources'][0]['fields'][] = ['name' => 'image', 'type' => 'media'];
        $this->assertSame([], self::lintDefinition($definition));

        $definition['resources'][] = ['id' => 'documents'] + $library;
        $this->assertSame(['resources.0.fields.1.resource: a media field needs a resource when there is not exactly one library.'], self::lintDefinition($definition));

        $definition['resources'][0]['fields'][1]['resource'] = 'pages';
        $this->assertSame(['resources.0.fields.1.resource: no resource of kind media named pages'], self::lintDefinition($definition));

        $definition['resources'][0]['fields'][1]['resource'] = 'documents';
        $definition['resources'][2]['fields'][0]['multiple'] = true;
        $this->assertSame(['resources.2.fields.0: the file field of a media resource must be a single file.'], self::lintDefinition($definition));

        $definition['resources'][2]['fields'] = [['name' => 'name', 'type' => 'text']];
        $this->assertSame(['resources.2.fields: a resource of kind media needs a file field.'], self::lintDefinition($definition));
    }

    public function test_components_of_the_openapi_document(): void
    {
        $this->assertMatchesComponent(['id' => '1', 'version' => null, 'abilities' => ['update' => true], 'title' => 'A'], 'Item');

        $this->expectException(AssertionFailedError::class);
        $this->assertMatchesComponent(['id' => 1, 'version' => null, 'abilities' => []], 'Item');
    }
}
