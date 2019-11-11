<?php

namespace Panneau\Tests\Unit\Support\Schemas;

use Panneau\Tests\TestCase;
use Panneau\Support\Schemas\Fields;
use Panneau\Support\Schemas\Field;

class FieldsTest extends TestCase
{
    public function testToFieldArray()
    {
        $field = Fields::make()
            ->withNamespace('data')
            ->addField(Field::make('title'));

        $this->assertEquals([
            'name' => 'data',
            'type' => 'fields',
            'label' => '',
            'fields' => [
                [
                    'name' => 'data.title',
                    'type' => 'field',
                    'label' => 'Title',
                ]
            ]
        ], $field->toFieldArray());
    }
}
