<?php

use Folklore\Panneau\Support\Schema;

/**
 * @coversDefaultClass Folklore\Panneau\Support\Schema
 */
class SchemaTest extends TestCase
{
    protected $schema;

    public function setUp()
    {
        parent::setUp();

        $this->schema = new Schema();
    }

    /**
     * Test setting model
     *
     * @test
     * @covers ::setModel
     * @covers ::getModel
     *
     */
    public function testModel()
    {
        $model = 'model';
        $this->schema->setModel($model);
        $returnedModel = $this->schema->getModel();
        $this->assertEquals($model, $returnedModel);
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

        $this->schema->addField('metadata', [
            'test' => true
        ]);
        $returnedFields = $this->schema->getFields();
        $this->assertEquals(['data', 'metadata' => [
            'test' => true
        ]], $returnedFields);
    }
}
