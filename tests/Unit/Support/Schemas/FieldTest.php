<?php

namespace Panneau\Tests\Unit\Support\Schemas;

use Panneau\Tests\TestCase;
use Panneau\Support\Schemas\Field;

class FieldTest extends TestCase
{
    public function testToFieldArray()
    {
        $field = Field::make('title')
            ->withNamespace('data');

        $this->assertEquals([
            'name' => 'data.title',
            'type' => 'field',
            'label' => 'Title',
        ], $field->toFieldArray());
    }
}
