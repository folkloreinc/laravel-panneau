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
     * Test getting properties
     *
     * @test
     * @covers ::setProperties
     * @covers ::getProperties
     *
     */
    public function testGetProperties()
    {
        $data = [
            'data' => [
                'type' => 'integer'
            ]
        ];
        $this->schema->setProperties($data);
        $returnedData = $this->schema->getProperties();
        $this->assertEquals($data, $returnedData);
    }
}
