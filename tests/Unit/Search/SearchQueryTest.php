<?php

namespace Panneau\Tests\Unit\Search;

use Illuminate\Validation\ValidationException;
use Panneau\Search\SearchQuery;
use Panneau\Tests\TestCase;

class SearchQueryTest extends TestCase
{
    public function test_defaults(): void
    {
        $query = SearchQuery::fromParameters(['q' => ' été '], ['pages', 'articles']);

        $this->assertSame('été', $query->q);
        $this->assertSame(5, $query->limit);
        $this->assertSame(['pages', 'articles'], $query->resources);
    }

    public function test_resources_keep_the_order_of_the_definition_without_duplicates(): void
    {
        $query = SearchQuery::fromParameters(['q' => 'été', 'limit' => '20', 'resources' => 'articles, pages,articles'], ['pages', 'articles', 'users']);

        $this->assertSame(20, $query->limit);
        $this->assertSame(['pages', 'articles'], $query->resources);
    }

    public function test_lengths_are_in_characters(): void
    {
        $this->assertSame('éé', SearchQuery::fromParameters(['q' => 'éé'], [])->q);

        $this->expectException(ValidationException::class);
        SearchQuery::fromParameters(['q' => str_repeat('é', 256)], []);
    }

    public function test_invalid_utf8_is_invalid(): void
    {
        try {
            SearchQuery::fromParameters(['q' => "\xff\xfe"], []);
            $this->fail('No exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['q'], array_keys($exception->errors()));
        }
    }
}
