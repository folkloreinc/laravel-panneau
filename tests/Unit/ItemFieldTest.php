<?php

namespace Panneau\Tests\Unit;

use Illuminate\Http\Request;
use Panneau\Fields\Item;
use Panneau\Tests\TestCase;
use TestApp\Fields\AuthorItem;

class ItemFieldTest extends TestCase
{
    public function testItemWithoutFields()
    {
        $field = Item::make('author')->withRules(['array']);
        $data = ['author' => ['id' => '1']];

        $this->assertNull($field->fields());
        $this->assertEquals(
            ['author' => ['array']],
            $field->getRulesFromRequest(Request::create('/'))
        );
        $this->assertEquals($data, $field->prepareRequestData($data));
    }

    public function testItemWithFields()
    {
        $field = AuthorItem::make('author');

        $this->assertEquals(
            ['author.name' => ['string', 'max:10']],
            $field->getRulesFromRequest(Request::create('/'))
        );
    }
}
