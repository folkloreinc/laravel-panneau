<?php

use Folklore\Panneau\Panneau;

/**
 * @coversDefaultClass Folklore\Panneau\Panneau
 */
class PanneauTest extends TestCase
{
    protected $panneau;

    public function setUp()
    {
        parent::setUp();

        $this->panneau = new Panneau(app());
    }

    /**
     * Test adding a schema
     *
     * @test
     * @covers ::__construct
     * @covers ::addSchema
     * @covers ::schemas
     * @covers ::schema
     *
     */
    public function testAddSchema()
    {
        $this->panneau->addSchema('test', \BubbleTestSchema::class);
        $schemas = $this->panneau->schemas();
        $this->assertArrayHasKey('test', $schemas);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['test']);
        $schema = $this->panneau->schema('test');
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }

    /**
     * Test adding a schema to a namespace
     *
     * @test
     * @covers ::__construct
     * @covers ::addSchema
     * @covers ::schemas
     * @covers ::schema
     *
     */
    public function testAddSchemaWithNamespace()
    {
        $this->panneau->addSchema('test', \BubbleTestSchema::class, \Folklore\Panneau\Models\Page::class);
        $schemas = $this->panneau->schemas(\Folklore\Panneau\Models\Page::class);
        $this->assertArrayHasKey('test', $schemas);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['test']);
        $schema = $this->panneau->schema('test', \Folklore\Panneau\Models\Page::class);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schema);
    }

    /**
     * Test adding multiple schemas
     *
     * @test
     * @covers ::__construct
     * @covers ::addSchemas
     * @covers ::addSchema
     * @covers ::schemas
     *
     */
    public function testAddSchemas()
    {
        $this->panneau->addSchemas([
            'test1' => \BubbleTestSchema::class,
            'test2' => \BubbleTestSchema::class,
        ]);
        $schemas = $this->panneau->schemas();
        $this->assertArrayHasKey('test1', $schemas);
        $this->assertArrayHasKey('test2', $schemas);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['test1']);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['test2']);
    }

    /**
     * Test adding multiple schemas with namespace
     *
     * @test
     * @covers ::__construct
     * @covers ::addSchemas
     * @covers ::addSchema
     * @covers ::schemas
     *
     */
    public function testAddSchemasWithNamespace()
    {
        $this->panneau->addSchemas([
            'test1' => \BubbleTestSchema::class,
            'test2' => \BubbleTestSchema::class,
        ], \Folklore\Panneau\Models\Page::class);
        $schemas = $this->panneau->schemas(\Folklore\Panneau\Models\Page::class);
        $this->assertArrayHasKey('test1', $schemas);
        $this->assertArrayHasKey('test2', $schemas);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['test1']);
        $this->assertInstanceOf(\BubbleTestSchema::class, $schemas['test2']);
    }
}
