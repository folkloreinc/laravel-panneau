<?php

use Folklore\Panneau\Contracts\Bubble as BubbleContract;

/**
 * @coversDefaultClass Folklore\Panneau\Support\Traits\HasFieldsSchema
 */
class HasFieldsSchemaTest extends TestCase
{
    protected $model;

    public function setUp()
    {
        parent::setUp();

        TestModel::addSchema('default', \BubbleTestSchema::class);
    }

    /**
     * Test getting schemas
     *
     * @test
     * @covers ::schemas
     *
     */
    public function testSchemas()
    {
        $schemas = TestModel::schemas();
        $this->assertArrayHasKey('default', $schemas);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['default']);
    }

    /**
     * Test getting schema
     *
     * @test
     * @covers ::schema
     *
     */
    public function testSchema()
    {
        $schemas = TestModel::schemas();
        $schema = TestModel::schema();
        $this->assertEquals($schemas['default'], $schema);
    }

    /**
     * Test getting schema with name
     *
     * @test
     * @covers ::addSchema
     * @covers ::schema
     *
     */
    public function testSchemaWithName()
    {
        TestModel::addSchema('test', \BubbleTestSchema::class);
        $schemas = TestModel::schemas();
        $schema = TestModel::schema('test');
        $this->assertEquals($schemas['test'], $schema);
    }

    /**
     * Test getting the default schema from model
     *
     * @test
     * @covers ::getSchema
     *
     */
    public function testGetDefaultSchema()
    {
        $model = app(BubbleContract::class);
        $schema = $model->getSchema();
        $this->assertInstanceOf(config('panneau.schemas.'.BubbleContract::class.'.default'), $schema);
    }

    /**
     * Test getting the schema from the default column model
     *
     * @test
     * @covers ::addSchema
     * @covers ::getSchema
     * @covers ::setSchemaNameColumn
     *
     */
    public function testGetSchemaFromColumn()
    {
        $model = app(BubbleContract::class);
        $modelClass = get_class($model);
        $modelClass::addSchema('test', \BubbleTestSchema::class);
        $model->type = 'test';
        $schema = $model->getSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);

        $model->setSchemaNameColumn('schema');
        $model->schema = 'test';
        $schema = $model->getSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }

    /**
     * Test getting the schema from the column method
     *
     * @test
     * @covers ::addSchema
     * @covers ::getSchema
     *
     */
    public function testGetSchemaFromColumnMethod()
    {
        $model = new TestColumnModel();
        $modelClass = get_class($model);
        $modelClass::addSchema('test', \BubbleTestSchema::class);
        $model->schema = 'test';
        $schema = $model->getSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }

    /**
     * Test getting the schema from a method
     *
     * @test
     * @covers ::addSchema
     * @covers ::getSchema
     *
     */
    public function testGetSchemaFromMethod()
    {
        $model = new TestModel();
        $modelClass = get_class($model);
        $modelClass::addSchema('test', \BubbleTestSchema::class);
        $schema = $model->getSchema();
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }
}
