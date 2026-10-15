<?php

namespace Panneau\Tests\Unit\Lists;

use Panneau\Lists\Column;
use Panneau\Lists\Filters\DateFilter;
use Panneau\Lists\Filters\RelationFilter;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\Filters\TextFilter;
use Panneau\Lists\Filters\ToggleFilter;
use Panneau\Lists\ResourceList;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
use Panneau\Query\SortDirection;
use PHPUnit\Framework\TestCase;
use TestApp\Models\PostStatus;

class ResourceListTest extends TestCase
{
    private function list(): ResourceList
    {
        return (new ResourceList)
            ->columns(['title', Column::make('status')->label('State')->align('center')->sortable(false), Column::id('preview')->width(80)])
            ->filters([
                SelectFilter::make('status')->options(PostStatus::class)->multiple()->label('Status'),
                ToggleFilter::make('featured')->default(true),
                TextFilter::make('slug'),
                DateFilter::make('day'),
                RelationFilter::make('author')->resource('authors'),
            ])
            ->sorts(['title', 'published_at'])
            ->defaultSort('-published_at,title')
            ->search(['title'])
            ->layouts(['table', 'cards'])
            ->perPage(25)
            ->perPageOptions([25, 50])
            ->inlineEdit(['title'])
            ->selectable();
    }

    public function test_definition(): void
    {
        $definition = $this->list()->toDefinition(searchable: true);

        $this->assertSame([
            ['field' => 'title', 'sortable' => true],
            ['field' => 'status', 'label' => 'State', 'align' => 'center'],
            ['id' => 'preview', 'width' => 80],
        ], $definition['columns']);
        $this->assertSame(['id' => 'featured', 'type' => 'toggle', 'default' => true], $definition['filters'][1]);
        $this->assertSame(['id' => 'author', 'type' => 'relation', 'resource' => 'authors'], $definition['filters'][4]);
        $this->assertSame('-published_at,title', $definition['defaultSort']);
        $this->assertTrue($definition['search']);
        $this->assertSame(['table', 'cards'], $definition['layouts']);
        $this->assertSame(25, $definition['perPage']);
        $this->assertSame([25, 50], $definition['perPageOptions']);
        $this->assertSame(['title'], $definition['inlineEdit']);
        $this->assertTrue($definition['selectable']);
        $this->assertFalse($this->list()->toDefinition(searchable: false)['search']);
    }

    public function test_query_options(): void
    {
        $options = $this->list()->queryOptions(true, ['title', 'status'], 20, 100);

        $this->assertSame(['title', 'published_at'], $options->sorts);
        $this->assertEquals([new Sort('published_at', SortDirection::Descending), new Sort('title')], $options->defaultSort);
        $this->assertSame(['title'], $options->searchFields);
        $this->assertSame(['title', 'status'], $options->fields);
        $this->assertSame(25, $options->perPage);
        $this->assertSame([], $this->list()->queryOptions(false, null, 20, 100)->searchFields);
    }

    public function test_filters_normalize_their_values(): void
    {
        $options = $this->list()->queryOptions(true, null, 20, 100);
        $query = fn (array $filter): array => ResourceQuery::fromParameters(['filter' => $filter], $options)->filters;

        $this->assertSame(['status' => ['draft']], $query(['status' => 'draft']));
        $this->assertSame(['status' => ['draft', 'published']], $query(['status' => ['draft', 'deleted', 'published']]));
        $this->assertSame([], $query(['status' => 'deleted']));
        $this->assertSame(['featured' => false], $query(['featured' => '0']));
        $this->assertSame([], $query(['featured' => 'maybe']));
        $this->assertSame(['slug' => 'abc'], $query(['slug' => ' abc ']));
        $this->assertSame(['day' => '2026-10-15'], $query(['day' => '2026-10-15']));
        $this->assertSame([], $query(['day' => '2026-02-30']));
        $this->assertSame(['author' => '12'], $query(['author' => '12']));
        $this->assertSame([], $query(['author' => ['12']]));
    }
}
