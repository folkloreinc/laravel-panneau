<?php

namespace Panneau\Tests\Unit\Lists;

use Panneau\Fields\Date;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Lists\Column;
use Panneau\Lists\Filters\DateFilter;
use Panneau\Lists\Filters\DateRangeFilter;
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
            ->columns([
                Column::make('title')->hideable(false),
                Column::make('status')->label('State')->align('center')->sortable(false),
                Column::id('preview')->width(80)->hidden(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PostStatus::class)->multiple()->label('Status'),
                ToggleFilter::make('featured')->default(true),
                TextFilter::make('slug'),
                DateFilter::make('day'),
                RelationFilter::make('author')->resource('authors'),
                DateRangeFilter::make('published'),
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
            ['field' => 'title', 'sortable' => true, 'hideable' => false],
            ['field' => 'status', 'label' => 'State', 'align' => 'center'],
            ['id' => 'preview', 'width' => 80, 'hidden' => true],
        ], $definition['columns']);
        $this->assertSame(['id' => 'featured', 'type' => 'toggle', 'default' => true], $definition['filters'][1]);
        $this->assertSame(['id' => 'author', 'type' => 'relation', 'resource' => 'authors'], $definition['filters'][4]);
        $this->assertSame(['id' => 'published', 'type' => 'date-range'], $definition['filters'][5]);
        $this->assertSame('-published_at,title', $definition['defaultSort']);
        $this->assertTrue($definition['search']);
        $this->assertSame(['table', 'cards'], $definition['layouts']);
        $this->assertSame(25, $definition['perPage']);
        $this->assertSame([25, 50], $definition['perPageOptions']);
        $this->assertSame(['title'], $definition['inlineEdit']);
        $this->assertTrue($definition['selectable']);
        $this->assertFalse($this->list()->toDefinition(searchable: false)['search']);
        $this->assertArrayNotHasKey('pagination', $definition);
    }

    public function test_calendar(): void
    {
        $this->assertArrayNotHasKey('calendar', $this->list()->toDefinition(searchable: true));
        $this->assertSame(['field' => 'published_at'], $this->list()->calendar('published_at')->toDefinition(searchable: true)['calendar']);
        $this->assertSame(
            ['field' => 'published_at', 'filter' => 'published', 'perPage' => 200],
            $this->list()->calendar('published_at', filter: 'published', perPage: 200)->toDefinition(searchable: true)['calendar'],
        );
        // On the days of a range (panneau-js decision 0053), through its date-range filter
        $this->assertSame(['field' => 'dates'], $this->list()->calendar('dates')->toDefinition(searchable: true)['calendar']);
    }

    public function test_cursor_pagination(): void
    {
        $list = $this->list()->cursorPagination();

        $this->assertTrue($list->paginatesWithCursors());
        $this->assertSame('cursor', $list->toDefinition(searchable: true, cursors: true)['pagination']);
        // Only when the repository can: numbered pages otherwise
        $this->assertArrayNotHasKey('pagination', $list->toDefinition(searchable: true));
        $this->assertTrue($list->queryOptions(true, null, 20, 100, cursors: true)->cursorPagination);
        $this->assertFalse($list->queryOptions(true, null, 20, 100)->cursorPagination);
        $this->assertFalse($list->cursorPagination(false)->paginatesWithCursors());
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
        $this->assertSame(['published' => ['from' => '2026-01-01', 'to' => '2026-01-31']], $query(['published' => ['from' => '2026-01-01', 'to' => '2026-01-31']]));
        $this->assertSame(['published' => ['to' => '2026-01-31']], $query(['published' => ['to' => '2026-01-31']]));
        $this->assertSame(['published' => ['from' => '2026-01-01']], $query(['published' => ['from' => '2026-01-01', 'to' => '2026-02-30']]));
        $this->assertSame([], $query(['published' => ['from' => 'yesterday']]));
        $this->assertSame([], $query(['published' => '2026-01-01']));
        $this->assertSame([], $query(['published' => ['2026-01-01', '2026-01-31']]));
    }

    public function test_default_sorts(): void
    {
        $fields = [Text::make('title'), Text::make('subtitle'), Date::make('published_at'), Select::make('status')];

        // Without sorts nor columns: every sortable field, since the default columns are among them
        $this->assertSame(['title', 'subtitle', 'published_at', 'status'], (new ResourceList)->sortableFields($fields)->getSorts());
        // The sortable fields of the columns, but those of a column that isn't sortable
        $list = (new ResourceList)->sortableFields([...$fields, Textarea::make('summary')])->columns([
            'title',
            Column::make('status')->sortable(false),
            'published_at',
            'summary',
            Column::id('preview'),
        ]);
        $this->assertSame(['title', 'published_at'], $list->getSorts());
        $this->assertSame([
            ['field' => 'title', 'sortable' => true],
            ['field' => 'status'],
            ['field' => 'published_at', 'sortable' => true],
            ['field' => 'summary'],
            ['id' => 'preview'],
        ], $list->toDefinition(searchable: false)['columns']);
        $this->assertSame(['title', 'published_at'], $list->toDefinition(searchable: false)['sorts']);
        $this->assertSame(['title', 'published_at'], $list->queryOptions(false, null, 20, 100)->sorts);

        // Declared sorts win, and sorts([]) turns sorting off
        $this->assertSame(['subtitle'], (new ResourceList)->sortableFields($fields)->columns(['title', 'subtitle'])->sorts(['subtitle'])->getSorts());
        $off = (new ResourceList)->sortableFields($fields)->columns(['title'])->sorts([]);
        $this->assertSame([], $off->getSorts());
        $this->assertArrayNotHasKey('sorts', $off->toDefinition(searchable: false));
        $this->assertSame([['field' => 'title']], $off->toDefinition(searchable: false)['columns']);
    }

    public function test_a_sortable_column_can_be_sorted(): void
    {
        // Its header sorts: the query accepts its sort too
        $list = (new ResourceList)->columns(['title', Column::make('rank')->sortable()])->sorts(['title']);

        $this->assertSame(['title', 'rank'], $list->getSorts());
        $this->assertSame(['title', 'rank'], $list->queryOptions(false, null, 20, 100)->sorts);
    }
}
