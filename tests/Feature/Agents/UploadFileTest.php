<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Context\Context;
use Panneau\Context\ContextParam;
use Panneau\Events\AgentToolCalled;
use Panneau\Fields\File;
use Panneau\Resources\MediaResource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Http\Concerns\MakesUploads;
use Symfony\Component\HttpFoundation\Response;
use TestApp\Models\Article;
use TestApp\Models\Asset;
use TestApp\Models\User;
use TestApp\Panneau\Resources\ArticlesResource;
use TestApp\Panneau\Resources\DocumentsResource;
use TestApp\Panneau\Resources\ImagesResource;
use TestApp\Policies\AssetPolicy;

/**
 * `upload_file`, over the MCP server: the tool answers a signed URL of Panneau, and the file
 * sent there with one `PUT`, without a token, becomes a media of a library.
 */
#[WithMigration]
class UploadFileTest extends McpTestCase
{
    use MakesUploads;

    private User $user;

    private string $token;

    private string $defaultGuard;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filesystems.disks.files', ['driver' => 'local', 'root' => storage_path('app/files')]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultGuard = $this->app['config']->get('auth.defaults.guard');
        Storage::fake('public');
        Storage::fake('files');
        Panneau::allowWithoutPolicies();
        Panneau::resources([ImagesResource::class, ArticlesResource::class]);
        RecordingLibrary::$created = [];
        $this->user = $this->createUser();
        $this->token = $this->user->createToken('assistant', ['mcp:use', 'panneau:write'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_tool_answers_a_signed_url_and_the_put_makes_a_media(): void
    {
        Event::fake([AgentToolCalled::class]);

        $upload = $this->start(['resource' => 'images', 'name' => 'Cover 2026.png']);

        $this->assertSame(['resource', 'name', 'method', 'url', 'expiresIn'], array_keys($upload));
        $this->assertSame('images', $upload['resource']);
        $this->assertSame('PUT', $upload['method']);
        $this->assertSame(900, $upload['expiresIn']);
        $this->assertStringStartsWith('http://localhost/panneau/mcp/upload?', $upload['url']);
        parse_str((string) parse_url($upload['url'], PHP_URL_QUERY), $query);
        $this->assertEqualsCanonicalizing(['expires', 'upload', 'signature'], array_keys($query));
        // What the URL is for is encrypted: it shows neither the user nor the name
        $this->assertStringNotContainsString('Cover', $upload['url']);
        // The tool wrote nothing
        $this->assertSame(0, Asset::query()->count());
        Event::assertNotDispatched(AgentToolCalled::class);

        // No token: the URL alone
        $media = $this->send($upload['url'], self::png())->assertCreated()->json();

        $asset = Asset::query()->sole();
        $this->assertSame((string) $asset->getKey(), (string) $media['id']);
        $this->assertSame('Cover 2026.png', $media['file']['name']);
        $this->assertSame('image', $media['media']['type']);
        $this->assertSame('image/png', $media['media']['mime']);
        $this->assertIsString($media['version']);
        $this->assertIsArray($media['abilities']);
        // As the admin makes it: the scope and the type of the library, the disk of its file
        $this->assertSame(1, (int) $asset->team_id);
        $this->assertSame('image', $asset->type);
        $this->assertSame('public', $asset->file['disk']);
        $this->assertSame(self::png(), Storage::disk('public')->get($asset->file['path']));
        // As `get_item` gives it
        $this->assertEquals($this->callTool('get_item', ['resource' => 'images', 'id' => (string) $media['id']])->json('result.structuredContent'), $media);

        $tokenId = (string) $this->user->tokens()->sole()->getKey();
        Event::assertDispatchedTimes(AgentToolCalled::class, 1);
        Event::assertDispatched(AgentToolCalled::class, fn (AgentToolCalled $event): bool => $event->tool === 'upload_file'
            && $event->user?->getAuthIdentifier() === $this->user->getKey()
            && $event->agent?->id === $tokenId
            && $event->agent->name === 'assistant'
            && $event->resource === 'images'
            && $event->items === [(string) $media['id']]);
    }

    public function test_the_values_set_the_other_fields_of_the_media(): void
    {
        $upload = $this->start(['name' => 'cover.png', 'values' => ['name' => 'The cover', 'alt' => ['en' => 'A red square']]]);

        $media = $this->send($upload['url'], self::png())->assertCreated()->json();

        $this->assertSame('The cover', $media['name']);
        $this->assertSame('A red square', Asset::query()->sole()->alt['en']);

        // The file is the body, never a value
        $problem = $this->problem($this->callTool('upload_file', ['name' => 'cover.png', 'values' => ['file' => ['upload' => 'upl_1']]]));
        $this->assertSame(['values'], array_keys($problem['errors']));
        // Values that would make a URL too long for a server
        $problem = $this->problem($this->callTool('upload_file', ['name' => 'cover.png', 'values' => ['name' => str_repeat('a', 4000)]]));
        $this->assertSame(['values'], array_keys($problem['errors']));
    }

    public function test_the_id_of_the_media_is_the_value_of_a_media_field(): void
    {
        $media = $this->send($this->start(['name' => 'cover.png'])['url'], self::png())->assertCreated()->json();

        $this->callTool('save_item', ['resource' => 'articles', 'values' => ['title' => 'With a cover', 'cover' => (string) $media['id']]])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.cover.id', (string) $media['id']);

        $this->assertSame(1, Article::query()->count());
    }

    public function test_the_library_is_needed_when_the_user_can_create_in_several(): void
    {
        // One library: it is the one
        $this->assertSame('images', $this->start(['name' => 'cover.png'])['resource']);

        Panneau::resources([DocumentsResource::class]);
        $problem = $this->problem($this->callTool('upload_file', ['name' => 'cover.png']));
        $this->assertSame(422, $problem['status']);
        $this->assertSame(['Name the media library, one of: `images`, `documents`.'], $problem['errors']['resource']);
        $this->assertSame('documents', $this->start(['resource' => 'documents', 'name' => 'terms.pdf'])['resource']);

        // A library the user can't create in doesn't count, and gives no URL
        Gate::policy(LockedAsset::class, LockedAssetPolicy::class);
        Panneau::resources([LockedLibrary::class]);
        $problem = $this->problem($this->callTool('upload_file', ['name' => 'cover.png']));
        $this->assertSame(['Name the media library, one of: `images`, `documents`.'], $problem['errors']['resource']);
        $this->assertSame(403, $this->problem($this->callTool('upload_file', ['resource' => 'locked', 'name' => 'cover.png']))['status']);
    }

    public function test_a_resource_that_is_not_a_library_is_refused(): void
    {
        foreach (['articles', 'unknown'] as $resource) {
            $problem = $this->problem($this->callTool('upload_file', ['resource' => $resource, 'name' => 'cover.png']));

            $this->assertSame(422, $problem['status']);
            $this->assertSame(['`'.$resource.'` is not a media library. The libraries you can add files to: `images`.'], $problem['errors']['resource']);
        }
        $this->assertSame(['name'], array_keys($this->problem($this->callTool('upload_file', ['resource' => 'images']))['errors']));
        // The file never comes through the tool
        $this->assertSame(['content'], array_keys($this->problem($this->callTool('upload_file', ['name' => 'a.png', 'content' => 'aGVsbG8=']))['errors']));
    }

    public function test_a_token_that_reads_has_no_tool(): void
    {
        $token = $this->user->createToken('agent', ['mcp:use'])->plainTextToken;

        $this->rpc($token, 'tools/call', ['name' => 'upload_file', 'arguments' => ['name' => 'cover.png']])
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Tool [upload_file] not found.');
    }

    public function test_a_url_that_was_changed_is_refused(): void
    {
        Panneau::resources([DocumentsResource::class]);
        $images = $this->start(['resource' => 'images', 'name' => 'cover.png']);
        $documents = $this->start(['resource' => 'documents', 'name' => 'terms.pdf']);
        parse_str((string) parse_url($images['url'], PHP_URL_QUERY), $query);
        parse_str((string) parse_url($documents['url'], PHP_URL_QUERY), $other);
        $url = fn (array $changed): string => 'http://localhost/panneau/mcp/upload?'.http_build_query([...$query, ...$changed]);

        // The same URL, rebuilt, is the one that works
        $this->send($url([]), self::png())->assertCreated();

        foreach ([
            'signature' => ['signature' => str_repeat('0', 64)],
            'no signature' => ['signature' => null],
            'a later expiry' => ['expires' => (int) $query['expires'] + 3600],
            // What another URL of the same app was made for: another library
            'another upload' => ['upload' => $other['upload']],
            'no upload' => ['upload' => null],
        ] as $case => $changed) {
            $this->send($url($changed), self::PDF)->assertForbidden()->assertHeader('Content-Type', 'application/problem+json');
            $this->assertSame(1, Asset::query()->count(), $case);
        }
        // Claims this app didn't encrypt, even behind a valid signature
        $forged = url()->temporarySignedRoute('panneau.mcp.upload', now()->addMinutes(5), ['upload' => base64_encode('{"user":"1","resource":"images"}')], absolute: false);
        $this->send(url($forged), self::png())->assertForbidden();
        $this->assertSame(1, Asset::query()->count());
    }

    public function test_a_url_expires(): void
    {
        $this->app['config']->set('panneau.agents.uploads.url_ttl', 60);
        Carbon::setTestNow('2026-11-25 12:00:00');
        $upload = $this->start(['name' => 'cover.png']);
        $this->assertSame(60, $upload['expiresIn']);

        Carbon::setTestNow('2026-11-25 12:00:59');
        // Within its life, the URL makes a media each time: it isn't used up
        $this->send($upload['url'], self::png())->assertCreated();
        $this->send($upload['url'], self::png())->assertCreated();

        Carbon::setTestNow('2026-11-25 12:01:01');
        $this->send($upload['url'], self::png())->assertForbidden();
        $this->assertSame(2, Asset::query()->count());
    }

    public function test_the_file_is_checked_as_the_library_checks_it(): void
    {
        $upload = $this->start(['name' => 'cover.png']);

        // `accept`: the type of the contents, whatever the name says
        $response = $this->send($upload['url'], self::PDF)->assertStatus(422)->assertHeader('Content-Type', 'application/problem+json');
        $this->assertSame(['file'], array_keys($response->json('errors')));
        // `maxSize`: a million bytes for the images
        $response = $this->send($upload['url'], self::png().str_repeat("\0", 1_000_000))->assertStatus(422);
        $this->assertSame(['file'], array_keys($response->json('errors')));
        // The other values are validated too
        $invalid = $this->start(['name' => 'cover.png', 'values' => ['name' => ['not', 'a', 'string']]]);
        $response = $this->send($invalid['url'], self::png())->assertStatus(422);
        $this->assertSame(['name'], array_keys($response->json('errors')));

        $this->assertSame(0, Asset::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_body_over_the_limit_or_empty_is_refused(): void
    {
        $this->app['config']->set('panneau.agents.uploads.max_size', 100);
        $upload = $this->start(['name' => 'cover.png']);

        $this->send($upload['url'], str_repeat('a', 101))->assertStatus(413)->assertHeader('Content-Type', 'application/problem+json');
        // Whatever the length the client names
        $this->send($upload['url'], str_repeat('a', 101), ['CONTENT_LENGTH' => '10'])->assertStatus(413);
        $this->send($upload['url'], '')->assertStatus(400);
        $this->assertSame(0, Asset::query()->count());

        $this->send($upload['url'], self::png())->assertCreated();
    }

    public function test_the_user_is_checked_again_when_the_file_arrives(): void
    {
        $upload = $this->start(['name' => 'cover.png']);

        // The gates of the agents
        Gate::define('usePanneauAgents', fn (): bool => false);
        $this->send($upload['url'], self::png())->assertForbidden();
        Gate::define('usePanneauAgents', fn (): bool => true);

        // The policy of the library
        Gate::policy(Asset::class, LockedAssetPolicy::class);
        $this->send($upload['url'], self::png())->assertForbidden();
        Gate::policy(Asset::class, AssetPolicy::class);
        $this->assertSame(0, Asset::query()->count());
        $this->send($upload['url'], self::png())->assertCreated();
        Asset::query()->forceDelete();

        // A user who is gone
        $this->user->delete();
        $this->send($upload['url'], self::png())->assertForbidden();
        $this->assertSame(0, Asset::query()->count());
    }

    public function test_a_user_who_can_not_create_gets_no_url(): void
    {
        Gate::policy(Asset::class, LockedAssetPolicy::class);

        $problem = $this->problem($this->callTool('upload_file', ['resource' => 'images', 'name' => 'cover.png']));
        $this->assertSame(403, $problem['status']);
        $problem = $this->problem($this->callTool('upload_file', ['name' => 'cover.png']));
        $this->assertSame(['There is no media library you can add files to.'], $problem['errors']['resource']);
    }

    public function test_the_media_is_made_as_the_user_in_the_context_of_the_connection(): void
    {
        Panneau::context(fn (): array => [ContextParam::make('season')->options(['2025' => 'Season 2025', '2026' => 'Season 2026'])]);
        Panneau::resources([RecordingLibrary::class]);

        $upload = $this->start(['resource' => 'recording', 'name' => 'cover.png'], 'season/2026');
        // The URL has no prefix of a context: the context is in what it was made for
        $this->assertStringStartsWith('http://localhost/panneau/mcp/upload?', $upload['url']);
        $this->send($upload['url'], self::png())->assertCreated();

        $this->assertSame([['user' => $this->user->getKey(), 'context' => ['season' => '2026']]], RecordingLibrary::$created);
    }

    public function test_the_uploads_share_the_rate_limit_of_the_user(): void
    {
        $upload = $this->start(['name' => 'cover.png']);
        $this->app['config']->set('panneau.agents.rate_limit', 3);

        // The call of the tool counted for one
        $this->send($upload['url'], self::png())->assertCreated();
        $this->send($upload['url'], self::png())->assertCreated();
        $this->send($upload['url'], self::png())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(2, Asset::query()->count());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return TestResponse<Response>
     */
    private function callTool(string $tool, array $arguments, string $context = ''): TestResponse
    {
        return $this->rpc($this->token, 'tools/call', ['name' => $tool, 'arguments' => $arguments], $context);
    }

    /**
     * The answer of `upload_file`.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{resource: string, name: string, method: string, url: string, expiresIn: int}
     */
    private function start(array $arguments, string $context = ''): array
    {
        $response = $this->callTool('upload_file', $arguments, $context)->assertOk();
        $this->assertFalse($response->json('result.isError'), (string) $response->json('result.content.0.text'));

        return $response->json('result.structuredContent');
    }

    /**
     * Sends a file to a URL as `curl -T` does: the body, and no token.
     *
     * @param  array<string, string>  $server
     * @return TestResponse<Response>
     */
    private function send(string $url, string $contents, array $server = []): TestResponse
    {
        // A request of its own: nothing of the call of the tool is left, not even its guard
        $this->app['auth']->forgetGuards();
        $this->app['config']->set('auth.defaults.guard', $this->defaultGuard);

        return $this->call('PUT', $url, [], [], [], $server, $contents);
    }

    /**
     * The problem of a tool's result.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    private function problem(TestResponse $response): array
    {
        $response->assertOk()->assertJsonPath('result.isError', true);

        return (array) json_decode((string) $response->json('result.content.0.text'), true);
    }
}

/**
 * A library that records who creates its media, and in which context.
 */
class RecordingLibrary extends MediaResource
{
    /**
     * @var list<array{user: mixed, context: array<string, string>}>
     */
    public static array $created = [];

    public function id(): string
    {
        return 'recording';
    }

    public function model(): string
    {
        return Asset::class;
    }

    protected function file(File $file): File
    {
        return $file->disk('files');
    }

    public function fields(): array
    {
        return [];
    }

    public function creating(array $values, Request $request): array
    {
        self::$created[] = ['user' => $request->user()?->getAuthIdentifier(), 'context' => app(Context::class)->all()];

        return [...$values, 'team_id' => 1];
    }
}

/**
 * A library whose policy lets no one create.
 */
class LockedLibrary extends RecordingLibrary
{
    public function id(): string
    {
        return 'locked';
    }

    public function model(): string
    {
        return LockedAsset::class;
    }
}

class LockedAsset extends Asset
{
    protected $table = 'assets';
}

class LockedAssetPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }
}
