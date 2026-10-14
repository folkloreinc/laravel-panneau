<?php

namespace Panneau\Tests\Unit;

use Panneau\Fields\TextLocalized;
use Panneau\Support\LocalizedField;
use Panneau\Tests\TestCase;

class LocalizedFieldTest extends TestCase
{
    public function testLocalesFromIntlConfig()
    {
        config()->set('panneau.intl.locales', ['fr', 'en']);

        $this->assertEquals(['fr', 'en'], LocalizedField::getLocales());
        $this->assertEquals(
            ['fr', 'en'],
            array_keys(TextLocalized::make('title')->toArray()['properties'])
        );
    }

    public function testLocalesConfigIsStillSupported()
    {
        config()->set('panneau.intl.locales', ['fr', 'en']);
        config()->set('panneau.locales', ['en']);

        $this->assertEquals(['en'], LocalizedField::getLocales());
    }
}
