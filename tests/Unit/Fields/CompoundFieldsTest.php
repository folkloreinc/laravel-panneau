<?php

namespace Panneau\Tests\Unit\Fields;

use Panneau\Fields\Credits;
use Panneau\Fields\Group;
use Panneau\Fields\Repeater;
use Panneau\Fields\Text;
use Panneau\Tests\TestCase;

/**
 * Compound fields: types of plugins made of sub-fields (panneau-js decision 0065).
 */
class CompoundFieldsTest extends TestCase
{
    public function test_a_subclass_of_a_group_or_a_repeater_is_a_compound_field(): void
    {
        $team = new class('team') extends Repeater
        {
            public function type(): string
            {
                return 'team';
            }
        };
        $this->assertSame(
            ['name' => 'team', 'type' => 'team', 'fields' => [['name' => 'role', 'type' => 'text']], 'multiple' => true],
            $team->fields([Text::make('role')])->toDefinition(),
        );
        $seo = new class('seo') extends Group
        {
            public function type(): string
            {
                return 'seo';
            }
        };
        $this->assertSame(
            ['name' => 'seo', 'type' => 'seo', 'fields' => [['name' => 'title', 'type' => 'text']]],
            $seo->fields([Text::make('title')])->toDefinition(),
        );
        // A repeater stays a repeater, without multiple
        $this->assertArrayNotHasKey('multiple', Repeater::make('links')->fields([Text::make('url')])->toDefinition());
    }

    public function test_credits_name_people_and_their_roles(): void
    {
        $this->assertSame(
            [
                'name' => 'credits',
                'type' => 'credits',
                'fields' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Name'],
                    ['name' => 'roles', 'type' => 'text', 'label' => 'Roles'],
                    ['name' => 'featured', 'type' => 'boolean', 'label' => 'Featured'],
                ],
                'multiple' => true,
            ],
            Credits::make('credits')->toDefinition(),
        );
        $definition = Credits::make('credits')->people('people')->roles('roles')->append([Text::make('note')])->toDefinition();
        $this->assertSame(['person', 'name', 'roles', 'featured', 'note'], array_column($definition['fields'], 'name'));
        $this->assertSame(['type' => 'relation', 'resource' => 'people'], array_intersect_key($definition['fields'][0], ['type' => 1, 'resource' => 1]));
        $this->assertSame(['field' => 'person', 'filled' => false], $definition['fields'][1]['visibleWhen']);
        $this->assertSame(['type' => 'relation', 'resource' => 'roles', 'multiple' => true], array_intersect_key($definition['fields'][2], ['type' => 1, 'resource' => 1, 'multiple' => 1]));
        // Without names, a credit names a person of the resource
        $this->assertSame(
            ['person', 'roles', 'featured'],
            array_column(Credits::make('credits')->people('people')->names(false)->toDefinition()['fields'], 'name'),
        );
    }
}
