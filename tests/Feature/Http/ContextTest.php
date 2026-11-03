<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use LogicException;
use Panneau\Context\Context;
use Panneau\Context\ContextOption;
use Panneau\Context\ContextParam;
use Panneau\Context\ContextResolver;
use Panneau\Http\Problem;
use Panneau\Navigation\MenuItem;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Symfony\Component\HttpFoundation\Response;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\ContextPostsResource;

/**
 * The current context (panneau-js decision 0045): params declared with `Panneau::context()`,
 * values sent in `Panneau-Context`, checked for the user, and applied in the scope of resources.
 */
class ContextTest extends HttpTestCase
{
    use ValidatesProtocol;

    private Author $ada;

    private Author $grace;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([ContextPostsResource::class]);
        $this->ada = Author::query()->create(['name' => 'Ada Lovelace']);
        $this->grace = Author::query()->create(['name' => 'Grace Hopper']);
        // Authors named "Hidden" are not for the user
        Gate::before(fn (?Authenticatable $user, string $ability, array $arguments): ?bool => $ability === 'view' && ($arguments[0] ?? null) instanceof Author && $arguments[0]->name === 'Hidden' ? false : null);
    }

    /**
     * An author, then a post of that author, then a season among inline options.
     */
    private function declareContext(?ContextParam $author = null): void
    {
        Panneau::context(fn (): array => [
            $author ?? ContextParam::make('author')->label('Author')->resource(AuthorsResource::class)->required(),
            ContextParam::make('post')->label('Post')->allLabel('All posts')->resource(ContextPostsResource::class),
            ContextParam::make('season')->allLabel('All seasons')->options(['2025' => 'Season 2025', '2026' => 'Season 2026']),
        ]);
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private static function header(array $values): array
    {
        return ['Panneau-Context' => http_build_query($values)];
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<string, array<string, mixed>>
     */
    private static function params(TestResponse $response): array
    {
        $params = $response->json('context.params');
        self::assertIsArray($params);

        return array_column($params, null, 'name');
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertContextProblem(TestResponse $response, string $param): void
    {
        $response->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonPath('type', 'urn:panneau:problem:context')
            ->assertJsonPath('status', 403)
            ->assertJsonPath('param', $param);
        $this->assertContains('Panneau-Context', $response->baseResponse->getVary());
    }

    public function test_without_a_context_nothing_changes(): void
    {
        $response = $this->getJson(self::API.'/definition', self::header(['author' => '999']))->assertOk();

        $this->assertArrayNotHasKey('context', (array) $response->json());
        $this->assertNotContains('Panneau-Context', $response->baseResponse->getVary());
        $this->getJson(self::API.'/context/author/options')->assertNotFound();
    }

    public function test_the_definition_declares_the_params(): void
    {
        $this->declareContext();

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition((array) $response->json());
        $this->assertContains('Panneau-Context', $response->baseResponse->getVary());
        $this->assertSame([
            ['name' => 'author', 'label' => 'Author', 'required' => true, 'value' => null, 'valueLabel' => null],
            ['name' => 'post', 'label' => 'Post', 'allLabel' => 'All posts', 'value' => null, 'valueLabel' => null],
            [
                'name' => 'season',
                'label' => 'Season',
                'allLabel' => 'All seasons',
                'options' => [['value' => '2025', 'label' => 'Season 2025'], ['value' => '2026', 'label' => 'Season 2026']],
                'value' => null,
                'valueLabel' => null,
            ],
        ], $response->json('context.params'));
    }

    public function test_the_definition_is_built_for_the_values_of_the_header(): void
    {
        $this->declareContext();
        $post = $this->createPost(['title' => 'Notes on the engine', 'author_id' => $this->ada->id]);

        $response = $this->getJson(self::API.'/definition', self::header([
            'author' => (string) $this->ada->id,
            'post' => (string) $post->id,
            'season' => '2026',
            // Names that aren't params are ignored
            'unknown' => 'whatever',
        ]))->assertOk();

        $this->assertValidDefinition((array) $response->json());
        $params = self::params($response);
        $this->assertSame([(string) $this->ada->id, 'Ada Lovelace'], [$params['author']['value'], $params['author']['valueLabel']]);
        $this->assertSame([(string) $post->id, 'Notes on the engine'], [$params['post']['value'], $params['post']['valueLabel']]);
        $this->assertSame(['2026', 'Season 2026'], [$params['season']['value'], $params['season']['valueLabel']]);
    }

    public function test_a_value_the_user_cannot_use_is_a_context_problem(): void
    {
        $this->declareContext();
        $hidden = Author::query()->create(['name' => 'Hidden']);
        $other = $this->createPost(['author_id' => $this->grace->id]);

        // An item that doesn't exist, or that the policy doesn't let the user view
        $this->assertContextProblem($this->getJson(self::API.'/definition', self::header(['author' => '999'])), 'author');
        $this->assertContextProblem($this->getJson(self::API.'/definition', self::header(['author' => (string) $hidden->id])), 'author');
        // A post that isn't in the scope of the author before it
        $this->assertContextProblem($this->getJson(self::API.'/definition', self::header(['author' => (string) $this->ada->id, 'post' => (string) $other->id])), 'post');
        // A value that isn't an inline option
        $this->assertContextProblem($this->getJson(self::API.'/definition', self::header(['season' => '1999'])), 'season');
        // On the other routes too
        $this->assertContextProblem($this->getJson(self::API.'/resources/posts', self::header(['author' => '999'])), 'author');
    }

    public function test_a_required_param_gets_its_default(): void
    {
        $this->declareContext(
            ContextParam::make('author')->resource(AuthorsResource::class)->required()
                ->default(fn (?Authenticatable $user, Context $context): ?string => $context->isEmpty() && $user !== null ? (string) $this->grace->id : null),
        );

        $params = self::params($this->getJson(self::API.'/definition')->assertOk());
        $this->assertSame([(string) $this->grace->id, 'Grace Hopper'], [$params['author']['value'], $params['author']['valueLabel']]);

        // A value in the header wins over the default
        $params = self::params($this->getJson(self::API.'/definition', self::header(['author' => (string) $this->ada->id]))->assertOk());
        $this->assertSame((string) $this->ada->id, $params['author']['value']);
    }

    public function test_a_default_the_user_cannot_use_is_left_out(): void
    {
        $hidden = Author::query()->create(['name' => 'Hidden']);
        $this->declareContext(
            ContextParam::make('author')->resource(AuthorsResource::class)->required()->default(fn (): string => (string) $hidden->id),
        );

        $params = self::params($this->getJson(self::API.'/definition')->assertOk());

        $this->assertNull($params['author']['value']);
    }

    public function test_the_options_of_a_resource_param(): void
    {
        $this->declareContext();
        Author::query()->create(['name' => 'Hidden']);

        $response = $this->getJson(self::API.'/context/author/options')->assertOk();
        $this->assertContains('Panneau-Context', $response->baseResponse->getVary());
        $this->assertSame([
            ['value' => (string) $this->ada->id, 'label' => 'Ada Lovelace'],
            ['value' => (string) $this->grace->id, 'label' => 'Grace Hopper'],
        ], $response->json('data'));

        $this->getJson(self::API.'/context/author/options?q=grace')
            ->assertOk()
            ->assertExactJson(['data' => [['value' => (string) $this->grace->id, 'label' => 'Grace Hopper']]]);
    }

    public function test_the_options_of_a_param_are_for_the_values_before_it(): void
    {
        $this->declareContext();
        $first = $this->createPost(['title' => 'Notes on the engine', 'author_id' => $this->ada->id]);
        $second = $this->createPost(['title' => 'Sketch of the engine', 'author_id' => $this->ada->id]);
        $this->createPost(['title' => 'Compilers', 'author_id' => $this->grace->id]);

        $this->getJson(self::API.'/context/post/options', self::header(['author' => (string) $this->ada->id]))
            ->assertOk()
            ->assertJsonPath('data.*.value', [(string) $first->id, (string) $second->id]);
        $this->getJson(self::API.'/context/post/options?q=sketch', self::header(['author' => (string) $this->ada->id]))
            ->assertOk()
            ->assertJsonPath('data.*.label', ['Sketch of the engine']);
        // Values after the param don't change its choices
        $this->getJson(self::API.'/context/author/options', self::header(['author' => (string) $this->ada->id, 'post' => (string) $first->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
        // Without the author, the posts need one
        $this->assertContextProblem($this->getJson(self::API.'/context/post/options'), 'author');
    }

    public function test_the_options_of_inline_options_and_of_closures(): void
    {
        Panneau::context([
            ContextParam::make('season')->options([['value' => '2026', 'label' => 'Season 2026', 'description' => 'This year'], ['value' => '2025', 'label' => 'Season 2025']]),
            ContextParam::make('episode')
                ->search(fn (string $q, Context $context): array => array_filter(
                    ['1' => 'Pilot of '.$context->label('season'), '2' => 'Finale of '.$context->label('season')],
                    fn (string $label): bool => $q === '' || stripos($label, $q) !== false,
                ))
                ->find(fn (string $value, Context $context): ?ContextOption => in_array($value, ['1', '2'], true) && $context->has('season')
                    ? new ContextOption($value, 'Episode '.$value)
                    : null),
        ]);

        $this->getJson(self::API.'/context/season/options?q=2026')
            ->assertOk()
            ->assertExactJson(['data' => [['value' => '2026', 'label' => 'Season 2026', 'description' => 'This year']]]);
        $this->getJson(self::API.'/context/episode/options?q=finale', self::header(['season' => '2026']))
            ->assertOk()
            ->assertExactJson(['data' => [['value' => '2', 'label' => 'Finale of Season 2026']]]);

        $params = self::params($this->getJson(self::API.'/definition', self::header(['season' => '2025', 'episode' => '2']))->assertOk());
        $this->assertSame(['2', 'Episode 2'], [$params['episode']['value'], $params['episode']['valueLabel']]);
        $this->assertArrayNotHasKey('options', $params['episode']);
        // The episode needs a season before it
        $this->assertContextProblem($this->getJson(self::API.'/definition', self::header(['episode' => '2'])), 'episode');
    }

    public function test_the_options_of_an_unknown_param(): void
    {
        $this->declareContext();

        $this->getJson(self::API.'/context/unknown/options')->assertNotFound()->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_resources_apply_the_context_in_their_scope(): void
    {
        $this->declareContext();
        $mine = $this->createPost(['title' => 'Notes on the engine', 'author_id' => $this->ada->id]);
        $theirs = $this->createPost(['title' => 'Compilers', 'author_id' => $this->grace->id]);
        $header = self::header(['author' => (string) $this->ada->id]);

        $this->getJson(self::API.'/resources/context-posts', $header)
            ->assertOk()
            ->assertJsonPath('data.*.id', [(string) $mine->id])
            ->assertJsonPath('meta.total', 1);
        $this->getJson(self::API.'/resources/context-posts/'.$mine->id, $header)->assertOk()->assertJsonPath('data.title', 'Notes on the engine');
        $this->getJson(self::API.'/resources/context-posts/'.$theirs->id, $header)->assertNotFound();
        // Created in the context
        $created = $this->postJson(self::API.'/resources/context-posts', ['title' => 'A new note'], $header)->assertCreated();
        $this->assertSame($this->ada->id, Post::query()->findOrFail($created->json('data.id'))->author_id);

        // Without a value of the author, the resource answers the context problem
        $this->assertContextProblem($this->getJson(self::API.'/resources/context-posts'), 'author');
    }

    public function test_errors_vary_with_the_context(): void
    {
        $this->declareContext();

        $response = $this->getJson(self::API.'/resources/context-posts/999', self::header(['author' => (string) $this->ada->id]))->assertNotFound();

        $this->assertContains('Panneau-Context', $response->baseResponse->getVary());
    }

    public function test_menus_can_depend_on_the_context(): void
    {
        $this->declareContext();
        Panneau::menu('main', fn (Context $context): array => $context->has('author')
            ? [MenuItem::resource(ContextPostsResource::class)->label('Posts of '.$context->label('author'))]
            : [MenuItem::resource(AuthorsResource::class)]);

        $this->getJson(self::API.'/definition')
            ->assertOk()
            ->assertJsonPath('menus.main', [['type' => 'resource', 'resource' => 'authors']]);
        $this->getJson(self::API.'/definition', self::header(['author' => (string) $this->ada->id]))
            ->assertOk()
            ->assertJsonPath('menus.main', [['type' => 'resource', 'resource' => 'context-posts', 'label' => 'Posts of Ada Lovelace']]);
    }

    public function test_declarations_are_checked_when_they_are_read(): void
    {
        $resolve = fn (): Context => $this->app->make(ContextResolver::class)->resolve(['client' => '4'], auth()->user(), request());
        foreach ([
            'two params named "season"' => [ContextParam::make('season')->options([]), ContextParam::make('season')->options([])],
            'which is not registered' => [ContextParam::make('client')->resource('clients')],
            'a param that is a string' => ['client'],
        ] as $message => $params) {
            Panneau::context(fn (): array => $params);
            try {
                $resolve();
                $this->fail('The context was accepted: '.$message);
            } catch (LogicException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function test_the_page_of_the_app_inlines_the_definition_for_the_prefix_of_its_path(): void
    {
        $this->withoutVite();
        $this->declareContext();

        $boot = $this->bootConfig($this->get('/panneau/author/'.$this->ada->id.'/season/2025/resources/context-posts')->assertOk());
        $this->assertValidBootConfig($boot);
        $params = array_column($boot['definition']['context']['params'], null, 'name');
        $this->assertSame([(string) $this->ada->id, 'Ada Lovelace'], [$params['author']['value'], $params['author']['valueLabel']]);
        $this->assertNull($params['post']['value']);
        $this->assertSame('2025', $params['season']['value']);

        // Without a prefix, no values
        $boot = $this->bootConfig($this->get('/panneau/resources/posts')->assertOk());
        $this->assertNull($boot['definition']['context']['params'][0]['value']);

        // A value the user can't use: the app fetches the definition and gets the problem
        $boot = $this->bootConfig($this->get('/panneau/author/999/resources/posts')->assertOk());
        $this->assertValidBootConfig($boot);
        $this->assertArrayNotHasKey('definition', $boot);
    }

    /**
     * The boot config written in a page.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    private function bootConfig(TestResponse $response): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="panneau-boot">(.*?)</script>#s', (string) $response->getContent(), $matches));
        $boot = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($boot);

        return $boot;
    }
}
