<?php

namespace Panneau\Tests\Unit\Query;

use Panneau\Query\QueryOptions;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
use Panneau\Query\SortDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResourceQueryTest extends TestCase
{
    private function options(): QueryOptions
    {
        return new QueryOptions(
            filters: [
                'status',
                'tags',
                'featured' => fn (mixed $value): ?bool => match ($value) {
                    'true', '1' => true,
                    'false', '0' => false,
                    default => null,
                },
            ],
            sorts: ['title', 'published_at'],
            defaultSort: [new Sort('published_at', SortDirection::Descending)],
            searchFields: ['title', 'body'],
            fields: ['title', 'status', 'body'],
            perPage: 20,
            maxPerPage: 50,
        );
    }

    public function test_defaults_without_parameters(): void
    {
        $query = ResourceQuery::fromParameters([], $this->options(), ['team_id' => 3]);

        $this->assertSame(1, $query->page);
        $this->assertSame(20, $query->perPage);
        $this->assertEquals([new Sort('published_at', SortDirection::Descending)], $query->sorts);
        $this->assertSame([], $query->filters);
        $this->assertNull($query->search);
        $this->assertSame(['title', 'body'], $query->searchFields);
        $this->assertNull($query->fields);
        $this->assertSame(['team_id' => 3], $query->scope);
        $this->assertNull($query->ids);
        $this->assertSame([], $query->except);
    }

    public function test_page_and_per_page(): void
    {
        $query = ResourceQuery::fromParameters(['page' => '3', 'perPage' => '10'], $this->options());

        $this->assertSame(3, $query->page);
        $this->assertSame(10, $query->perPage);
    }

    public function test_per_page_is_capped(): void
    {
        $query = ResourceQuery::fromParameters(['perPage' => '1000'], $this->options());

        $this->assertSame(50, $query->perPage);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidIntegers(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-2'],
            'decimal' => ['1.5'],
            'text' => ['abc'],
            'array' => [['2']],
            'empty' => [''],
            'too large' => ['99999999999999999999'],
        ];
    }

    #[DataProvider('invalidIntegers')]
    public function test_invalid_page_and_per_page_fall_back_to_defaults(mixed $value): void
    {
        $query = ResourceQuery::fromParameters(['page' => $value, 'perPage' => $value], $this->options());

        $this->assertSame(1, $query->page);
        $this->assertSame(20, $query->perPage);
    }

    public function test_sorts_keep_only_declared_fields(): void
    {
        $query = ResourceQuery::fromParameters(
            ['sort' => '-title,secret,published_at,-title, ,-'],
            $this->options(),
        );

        $this->assertEquals(
            [new Sort('title', SortDirection::Descending), new Sort('published_at')],
            $query->sorts,
        );
    }

    public function test_undeclared_sorts_fall_back_to_the_default_sort(): void
    {
        $query = ResourceQuery::fromParameters(['sort' => 'secret'], $this->options());

        $this->assertEquals([new Sort('published_at', SortDirection::Descending)], $query->sorts);
    }

    public function test_filters_keep_only_declared_filters(): void
    {
        $query = ResourceQuery::fromParameters(
            ['filter' => ['status' => 'published', 'secret' => 'x', 'tags' => ['a', 'b']]],
            $this->options(),
        );

        $this->assertSame(['status' => 'published', 'tags' => ['a', 'b']], $query->filters);
        $this->assertSame('published', $query->filter('status'));
        $this->assertNull($query->filter('secret'));
    }

    public function test_filters_drop_empty_and_nested_values(): void
    {
        $query = ResourceQuery::fromParameters(
            ['filter' => ['status' => '', 'tags' => [['nested'], '']]],
            $this->options(),
        );

        $this->assertSame([], $query->filters);
    }

    public function test_filters_keep_the_bounds_of_a_range(): void
    {
        $query = ResourceQuery::fromParameters(
            ['filter' => ['status' => ['from' => '2026-01-01', 'to' => '2026-01-31', 'other' => 'x'], 'tags' => ['to' => '', 'from' => ['nested']]]],
            $this->options(),
        );

        $this->assertSame(['status' => ['from' => '2026-01-01', 'to' => '2026-01-31']], $query->filters);
    }

    public function test_filters_go_through_their_normalizer(): void
    {
        $this->assertSame(
            ['featured' => true],
            ResourceQuery::fromParameters(['filter' => ['featured' => 'true']], $this->options())->filters,
        );
        $this->assertSame(
            [],
            ResourceQuery::fromParameters(['filter' => ['featured' => 'maybe']], $this->options())->filters,
        );
    }

    public function test_filter_must_be_an_array(): void
    {
        $query = ResourceQuery::fromParameters(['filter' => 'status'], $this->options());

        $this->assertSame([], $query->filters);
    }

    public function test_search(): void
    {
        $query = ResourceQuery::fromParameters(['q' => '  été  '], $this->options());

        $this->assertSame('été', $query->search);
    }

    public function test_search_is_ignored_without_search_fields(): void
    {
        $query = ResourceQuery::fromParameters(['q' => 'été'], new QueryOptions);

        $this->assertNull($query->search);
    }

    public function test_search_is_truncated(): void
    {
        $query = ResourceQuery::fromParameters(['q' => str_repeat('a', 1000)], $this->options());

        $this->assertSame(ResourceQuery::MAX_SEARCH_LENGTH, mb_strlen((string) $query->search));
    }

    public function test_fields_keep_only_declared_fields(): void
    {
        $query = ResourceQuery::fromParameters(['fields' => 'title, secret,status,title'], $this->options());

        $this->assertSame(['title', 'status'], $query->fields);
        $this->assertTrue($query->wantsField('title'));
        $this->assertFalse($query->wantsField('body'));
    }

    public function test_fields_are_not_restricted_without_the_parameter(): void
    {
        $query = ResourceQuery::fromParameters([], $this->options());

        $this->assertTrue($query->wantsField('body'));
    }

    public function test_with_methods_return_copies(): void
    {
        $query = new ResourceQuery;
        $copy = $query
            ->withScope(['team_id' => 1])
            ->withIds(['1', '2', '1'])
            ->withExcept(['2'])
            ->withPage(2, 5)
            ->withSorts([new Sort('title')]);

        $this->assertSame([], $query->scope);
        $this->assertSame(['team_id' => 1], $copy->scope);
        $this->assertSame(['1', '2'], $copy->ids);
        $this->assertSame(['2'], $copy->except);
        $this->assertSame(2, $copy->page);
        $this->assertSame(5, $copy->perPage);
        $this->assertEquals([new Sort('title')], $copy->sorts);
    }

    public function test_sort_parsing_and_formatting(): void
    {
        $this->assertEquals(new Sort('title', SortDirection::Descending), Sort::parse('-title'));
        $this->assertSame('-title', Sort::parse('-title')->toString());
        $this->assertSame('title', Sort::parse('title')->toString());
        $this->assertEquals([new Sort('a'), new Sort('b', SortDirection::Descending)], Sort::parseList('a,,-b'));
    }
}
