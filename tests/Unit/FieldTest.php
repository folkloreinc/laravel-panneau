<?php

namespace Panneau\Tests\Unit;

use Panneau\Fields\Text;
use Panneau\Tests\TestCase;

class FieldTest extends TestCase
{
    public function testWithPropertiesOnFieldWithoutProperties()
    {
        $field = Text::make('title')->withProperties(['en' => Text::make('en')]);

        $this->assertEquals(['en'], array_keys($field->properties()));
    }

    public function testWithPropertiesMergesProperties()
    {
        $field = Text::make('title')
            ->withProperties(['en' => Text::make('en')])
            ->withProperties(['fr' => Text::make('fr')]);

        $this->assertEquals(['en', 'fr'], array_keys($field->properties()));
    }
}
