<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Testing\TestResponse;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Symfony\Component\HttpFoundation\Response;
use TestApp\Models\Note;
use TestApp\Panneau\Resources\NotesFeedResource;

/**
 * A list paged with cursors (`ResourceList::cursorPagination()`, panneau-js decision 0039): the
 * notes feed, two notes a page, sorted by title.
 *
 * An unreadable cursor is a `422` (see `EloquentRepositoryTest`), which the `openapi.json` of the
 * protocol doesn't document yet for this operation.
 */
class CursorPaginationTest extends HttpTestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::allowWithoutPolicies();
        Panneau::resources([NotesFeedResource::class]);
    }

    private function createNotes(string ...$titles): void
    {
        foreach ($titles as $title) {
            Note::query()->create(['title' => $title, 'team_id' => 1]);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return TestResponse<Response>
     */
    private function list(array $parameters = []): TestResponse
    {
        return $this->getJson(self::API.'/resources/notes-feed?'.http_build_query($parameters))->assertOk();
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<string>
     */
    private function titles(TestResponse $response): array
    {
        return array_column($response->json('data'), 'title');
    }

    public function test_the_definition_declares_the_pagination(): void
    {
        $definition = $this->getJson(self::API.'/definition')->assertOk()->json();
        $this->assertValidDefinition($definition);

        $resources = array_column($definition['resources'], null, 'id');
        $this->assertSame('cursor', $resources['notes-feed']['list']['pagination']);
        $this->assertArrayNotHasKey('pagination', $resources['posts']['list']);
    }

    public function test_the_pages_follow_their_cursors(): void
    {
        $this->createNotes('E', 'C', 'A', 'D', 'B');

        $first = $this->list();
        $this->assertSame(['A', 'B'], $this->titles($first));
        $this->assertSame(['perPage', 'nextCursor', 'previousCursor'], array_keys($first->json('meta')));
        $first->assertJsonPath('meta.perPage', 2)->assertJsonPath('meta.previousCursor', null);

        $second = $this->list(['cursor' => $first->json('meta.nextCursor')]);
        $this->assertSame(['C', 'D'], $this->titles($second));
        // The previous page is the first, which has no cursor
        $second->assertJsonPath('meta.previousCursor', null);

        $last = $this->list(['cursor' => $second->json('meta.nextCursor')]);
        $this->assertSame(['E'], $this->titles($last));
        $last->assertJsonPath('meta.nextCursor', null);

        $back = $this->list(['cursor' => $last->json('meta.previousCursor')]);
        $this->assertSame(['C', 'D'], $this->titles($back));
        $back->assertJsonPath('meta.previousCursor', null);
        $this->assertSame(['E'], $this->titles($this->list(['cursor' => $back->json('meta.nextCursor')])));
    }

    public function test_a_cursor_keeps_its_query(): void
    {
        $this->createNotes('A', 'B', 'C', 'D', 'E');
        Note::query()->create(['title' => 'Z', 'team_id' => 2]);

        $first = $this->list(['sort' => '-title', 'filter' => ['status' => 'draft'], 'perPage' => 3]);
        $this->assertSame(['E', 'D', 'C'], $this->titles($first));

        $next = $this->list(['sort' => '-title', 'filter' => ['status' => 'draft'], 'perPage' => 3, 'cursor' => $first->json('meta.nextCursor')]);
        $this->assertSame(['B', 'A'], $this->titles($next));
        $next->assertJsonPath('meta.nextCursor', null);
    }

    public function test_the_page_parameter_is_ignored(): void
    {
        $this->createNotes('A', 'B', 'C');

        $response = $this->list(['page' => 2]);

        $this->assertSame(['A', 'B'], $this->titles($response));
        $this->assertArrayNotHasKey('page', $response->json('meta'));
    }

    public function test_an_empty_list(): void
    {
        $this->list()->assertExactJson(['data' => [], 'meta' => ['perPage' => 2, 'nextCursor' => null, 'previousCursor' => null]]);
    }
}
