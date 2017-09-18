<?php

use Folklore\Panneau\Contracts\Bubble as BubbleContract;
use Folklore\Panneau\Models\Bubble as BubbleModel;

/**
 * @coversDefaultClass Folklore\Panneau\Support\Traits\HasFieldsSchema
 */
class HasFieldsSchemaTest extends TestCase
{
    protected $model;

    public function setUp()
    {
        parent::setUp();

        TestModel::addFieldsSchema('default', \BubbleTestSchema::class);
    }

    /**
     * Test getting schemas
     *
     * @test
     * @covers ::fieldsSchemas
     *
     */
    public function testSchemas()
    {
        $schemas = TestModel::fieldsSchemas();
        $this->assertArrayHasKey('default', $schemas);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['default']);
    }

    /**
     * Test getting schema
     *
     * @test
     * @covers ::fieldsSchema
     *
     */
    public function testSchema()
    {
        $schemas = TestModel::fieldsSchemas();
        $schema = TestModel::fieldsSchema();
        $this->assertEquals($schemas['default'], $schema);
    }

    /**
     * Test getting schema with name
     *
     * @test
     * @covers ::addFieldsSchema
     * @covers ::fieldsSchema
     *
     */
    public function testSchemaWithName()
    {
        TestModel::addFieldsSchema('test', \BubbleTestSchema::class);
        $schemas = TestModel::fieldsSchemas();
        $schema = TestModel::fieldsSchema('test');
        $this->assertEquals($schemas['test'], $schema);
    }

    /**
     * Test getting the default schema from model
     *
     * @test
     * @covers ::getFieldsSchema
     *
     */
    public function testGetDefaultSchema()
    {
        $model = app(BubbleContract::class);
        $schema = $model->getFieldsSchema();
        $this->assertInstanceOf(config('panneau.schemas.'.BubbleModel::class.'.default'), $schema);
    }

    /**
     * Test getting the schema from the default column model
     *
     * @test
     * @covers ::addFieldsSchema
     * @covers ::getFieldsSchema
     * @covers ::setFieldsSchemaNameColumn
     *
     */
    public function testGetSchemaFromColumn()
    {
        $model = app(BubbleContract::class);
        $modelClass = get_class($model);
        $modelClass::addFieldsSchema('test', \BubbleTestSchema::class);
        $model->type = 'test';
        $schema = $model->getFieldsSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);

        $model->setFieldsSchemaNameColumn('schema');
        $model->schema = 'test';
        $schema = $model->getFieldsSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }

    /**
     * Test getting the schema from the column method
     *
     * @test
     * @covers ::addFieldsSchema
     * @covers ::getFieldsSchema
     *
     */
    public function testGetSchemaFromColumnMethod()
    {
        $model = new TestColumnModel();
        $modelClass = get_class($model);
        $modelClass::addFieldsSchema('test', \BubbleTestSchema::class);
        $model->schema = 'test';
        $schema = $model->getFieldsSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }

    /**
     * Test getting the schema from a method
     *
     * @test
     * @covers ::addFieldsSchema
     * @covers ::getFieldsSchema
     *
     */
    public function testGetSchemaFromMethod()
    {
        $model = new TestModel();
        $modelClass = get_class($model);
        $modelClass::addFieldsSchema('test', \BubbleTestSchema::class);
        $schema = $model->getFieldsSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }
}
