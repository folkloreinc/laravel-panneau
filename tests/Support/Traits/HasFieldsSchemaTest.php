<?php

/**
 * @coversDefaultClass Folklore\Panneau\Support\Traits\HasFieldsSchema
 */
class HasFieldsSchemaTest extends TestCase
{
    protected $model;

    public function setUp()
    {
        parent::setUp();

        $this->model = new TestModel();
        TestModel::addSchema('default', \BubbleTestSchema::class);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
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
}
