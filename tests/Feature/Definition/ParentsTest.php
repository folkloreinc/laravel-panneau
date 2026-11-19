<?php

namespace Panneau\Tests\Feature\Definition;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Navigation\Page;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;

/**
 * The `parent` of resources and pages in the definition (panneau-js decision 0093): what the app
 * shows above the title of a page, as a back link or breadcrumbs.
 */
class ParentsTest extends TestCase
{
    use ValidatesProtocol;

    /**
     * A resource of tags, under another id, whose list belongs to a page.
     */
    private static function resourceIn(string $id, ?string $parent): Resource
    {
        return new class($id, $parent) extends TagsResource
        {
            public function __construct(private readonly string $resourceId, private readonly ?string $parentPage) {}

            public function id(): string
            {
                return $this->resourceId;
            }

            public function parent(): ?string
            {
                return $this->parentPage;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(?Authenticatable $user = null): array
    {
        return $this->app->make(DefinitionBuilder::class)->build($user);
    }

    public function test_resources_and_pages_name_their_parent(): void
    {
        Panneau::resources([PostsResource::class, AuthorsResource::class, TagsResource::class, self::resourceIn('emails', 'activity')]);
        Panneau::pages([
            Page::dashboard('activity')->label('Activity'),
            Page::make('usage', 'usage')->label('Usage')->parent('activity'),
            // A class becomes the id of the resource
            Page::make('post-stats', 'post-stats')->params(['id'])->parent(resource: PostsResource::class),
            Page::make('email-stats', 'email-stats')->params(['id'])->parent(resource: 'emails'),
        ]);

        $definition = $this->definition();

        $this->assertValidDefinition($definition);
        $resources = array_column($definition['resources'], null, 'id');
        $this->assertSame('activity', $resources['emails']['parent']);
        $this->assertArrayNotHasKey('parent', $resources['posts']);
        $this->assertSame('usage', $definition['pages']['usage']['component']);
        $this->assertSame('activity', $definition['pages']['usage']['parent']);
        $this->assertSame(['resource' => 'posts'], $definition['pages']['post-stats']['parent']);
        $this->assertSame(['resource' => 'emails'], $definition['pages']['email-stats']['parent']);
        $this->assertArrayNotHasKey('parent', $definition['pages']['activity']);
    }

    public function test_pages_of_the_config_name_their_parent(): void
    {
        Panneau::resources([PostsResource::class, AuthorsResource::class, TagsResource::class]);
        $this->app['config']->set('panneau.pages', [
            'activity' => ['component' => 'activity', 'label' => 'Activity'],
            'usage' => ['component' => 'usage', 'parent' => 'activity'],
            'post-stats' => ['component' => 'post-stats', 'params' => ['id'], 'parent' => ['resource' => PostsResource::class]],
            'help' => ['component' => 'help', 'parent' => null],
        ]);

        $definition = $this->definition();
        $pages = $definition['pages'];

        $this->assertValidDefinition($definition);
        $this->assertSame('activity', $pages['usage']['parent']);
        $this->assertSame(['resource' => 'posts'], $pages['post-stats']['parent']);
        $this->assertSame(['component' => 'help'], $pages['help']);
    }

    public function test_a_page_hidden_from_the_user_is_still_a_parent(): void
    {
        Panneau::resources([self::resourceIn('emails', 'activity')]);
        Panneau::pages([Page::dashboard('activity')->label('Activity')->can(fn (): bool => false)]);

        $definition = $this->definition();

        $this->assertValidDefinition($definition);
        $this->assertSame(['view' => false], $definition['pages']['activity']['abilities']);
        $this->assertSame('activity', $definition['resources'][0]['parent']);
    }

    /**
     * @return iterable<string, array{list<resource|class-string<resource>>, array<array-key, mixed>, string}>
     */
    public static function invalidParents(): iterable
    {
        yield 'a resource whose page is not declared' => [
            [self::resourceIn('emails', 'activty')],
            [Page::make('activity', 'activity')],
            'The resource "emails" has the parent "activty", which is not a page declared in panneau.pages or with Panneau::pages().',
        ];
        yield 'a resource in the home page' => [
            [self::resourceIn('emails', 'home')],
            [Page::dashboard('home')],
            'The resource "emails" has the parent "home": the home page is never a parent.',
        ];
        yield 'a page whose page is not declared' => [
            [],
            [Page::make('usage', 'usage')->parent('activity')],
            'The page "usage" has the parent "activity", which is not a page declared in panneau.pages or with Panneau::pages().',
        ];
        yield 'a page in a page with params' => [
            [],
            [Page::make('job', 'job')->params(['id']), Page::make('logs', 'logs')->parent('job')],
            'The page "logs" has the parent "job", which has params: no link leads to a page about one thing.',
        ];
        yield 'a page of the config that is its own parent' => [
            [],
            ['usage' => ['component' => 'usage', 'parent' => 'usage']],
            'The page "usage" is not its own parent.',
        ];
        yield 'a page whose resource is not registered' => [
            [PostsResource::class],
            [Page::make('stats', 'stats')->parent(resource: 'postz')],
            'The page "stats" has the parent resource "postz", which is not registered.',
        ];
        yield 'a parent of the config that is neither a page nor a resource' => [
            [],
            ['usage' => ['component' => 'usage', 'parent' => ['page' => 'activity']]],
            'The page "usage" has the parent {"page":"activity"}: a parent is the id of a page, or ["resource" => ...]',
        ];
        yield 'parents that come back to where they started' => [
            [self::resourceIn('emails', 'activity')],
            [Page::dashboard('activity')->parent(resource: 'emails')],
            'The parents of the resource "emails" come back to it: "emails" → "activity" → "emails".',
        ];
        yield 'pages that come back to where they started' => [
            [],
            [Page::make('a', 'a')->parent('b'), Page::make('b', 'b')->parent('c'), Page::make('c', 'c')->parent('a')],
            'The parents of the page "a" come back to it: "a" → "b" → "c" → "a".',
        ];
    }

    /**
     * @param  list<resource|class-string<resource>>  $resources
     * @param  array<array-key, mixed>  $pages
     */
    #[DataProvider('invalidParents')]
    public function test_invalid_parents_throw_when_the_definition_is_built(array $resources, array $pages, string $message): void
    {
        Panneau::resources($resources);
        Panneau::pages($pages);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->definition();
    }
}
