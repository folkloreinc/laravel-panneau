<?php

use Folklore\Panneau\Support\Layout;

class LayoutTest extends TestCase
{
    protected $layoutData = [
        'type' => 'normal',
        'header' => '',
        'footer' => true,
    ];

    public function testConstruct()
    {
        $layout = new Layout($this->layoutData);

        $this->assertEquals($this->layoutData['type'], $layout->getType());
        $this->assertEquals($this->layoutData['header'], $layout->getHeader());
        $this->assertEquals($this->layoutData['footer'], $layout->getFooter());
    }

    public function testToArray()
    {
        $layout = new Layout($this->layoutData);

        $this->assertEquals($this->layoutData, $layout->toArray());
    }
}
