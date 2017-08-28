<?php

use Folklore\Panneau\Support\Schema;
use Folklore\Panneau\Support\FieldsSchema;

/**
 * @coversDefaultClass Folklore\Panneau\Support\FieldsSchema
 */
class FieldsSchemaTest extends TestCase
{
    protected $schema;

    public function setUp()
    {
        parent::setUp();

        $this->schema = new FieldsSchema();
    }

    /**
     * Test getting fields
     *
     * @test
     * @covers ::setFields
     * @covers ::getFields
     *
     */
    public function testGetFields()
    {
        $fields = ['data'];
        $this->schema->setFields($fields);
        $returnedFields = $this->schema->getFields();
        $this->assertEquals($fields, $returnedFields);
    }

    /**
     * Test getting fields names
     *
     * @test
     * @covers ::setFields
     * @covers ::getFieldsNames
     *
     */
    public function testGetFieldsNames()
    {
        $fields = [
            'data',
            'metadata' => []
        ];
        $this->schema->setFields($fields);
        $returnedFields = $this->schema->getFieldsNames();
        $this->assertEquals(['data', 'metadata'], $returnedFields);
    }

    /**
     * Test getting fields
     *
     * @test
     * @covers ::addField
     * @covers ::getFields
     *
     */
    public function testAddField()
    {
        $this->schema->addField('data');
        $returnedFields = $this->schema->getFields();
        $this->assertEquals(['data'], $returnedFields);

        $field = [
            'test' => 'test'
        ];
        $this->schema->addField('metadata', $field);
        $returnedFields = $this->schema->getFields();
        $this->assertArrayHasKey('metadata', $returnedFields);
        $this->assertInstanceOf(Schema::class, $returnedFields['metadata']);
        $this->assertEquals($field['test'], $returnedFields['metadata']['test']);
    }
}
