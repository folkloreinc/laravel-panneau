<?php

namespace Panneau\Tests\Unit\Layout;

use Panneau\Layout\Column;
use Panneau\Layout\Component;
use Panneau\Layout\Field;
use Panneau\Layout\Row;
use Panneau\Layout\Section;
use Panneau\Layout\Sidebar;
use Panneau\Layout\Tab;
use Panneau\Layout\Tabs;
use Panneau\Layout\Text;
use PHPUnit\Framework\TestCase;

class LayoutTest extends TestCase
{
    public function test_nodes(): void
    {
        $node = Tabs::make([
            Tab::make('Content', [
                Row::make([Field::make('title')->width(8), Column::make([Field::make('status')])->width(4)]),
                Text::make('Help text')->visibleWhen(['field' => 'status', 'equals' => 'draft']),
                Component::make('preview', ['size' => 'small']),
            ]),
            Tab::make('Empty'),
        ]);

        $this->assertSame([
            'type' => 'tabs',
            'children' => [
                ['type' => 'tab', 'label' => 'Content', 'children' => [
                    ['type' => 'row', 'children' => [
                        ['type' => 'field', 'name' => 'title', 'width' => 8],
                        ['type' => 'column', 'width' => 4, 'children' => [['type' => 'field', 'name' => 'status']]],
                    ]],
                    ['type' => 'text', 'text' => 'Help text', 'visibleWhen' => ['field' => 'status', 'equals' => 'draft']],
                    ['type' => 'component', 'component' => 'preview', 'props' => ['size' => 'small']],
                ]],
                ['type' => 'tab', 'label' => 'Empty', 'children' => []],
            ],
        ], $node->toDefinition());
        $this->assertSame(['title', 'status'], $node->fieldNames());
    }

    public function test_sections_and_sidebars(): void
    {
        $this->assertSame(
            ['type' => 'section', 'label' => 'SEO', 'description' => 'Sharing', 'collapsible' => true, 'collapsed' => true, 'children' => []],
            Section::make('SEO')->description('Sharing')->collapsible(collapsed: true)->toDefinition(),
        );
        $this->assertSame(['type' => 'sidebar', 'children' => [['type' => 'field', 'name' => 'a']]], Sidebar::make([Field::make('a')])->toDefinition());
        $this->assertSame(12, Field::make('a')->width(20)->toDefinition()['width']);
    }
}
