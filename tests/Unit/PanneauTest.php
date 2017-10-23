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
     *
     */
    public function testAddSchema()
    {
        $this->assertEquals(true, true);
    }
}
