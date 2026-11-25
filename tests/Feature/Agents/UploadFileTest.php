<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Panneau\Agents\Tools\SaveItem;
use Panneau\Agents\Tools\UploadFile;
use Panneau\Contracts\UploadStorage;
use Panneau\Events\AgentToolCalled;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Http\Concerns\MakesUploads;
use Panneau\Tests\Feature\Http\FakeUploadStorage;
use Panneau\Tests\Feature\Http\HttpTestCase;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Upload;
use Panneau\Uploads\Uploads;
use Panneau\Uploads\UploadStatus;
use TestApp\Models\Report;
use TestApp\Panneau\Resources\ReportsResource;

/**
 * `upload_file`, the tool that gives an agent an upload, with direct uploads enabled: a file in
 * `content`, or sent to a presigned URL then completed.
 */
class UploadFileTest extends HttpTestCase
{
    use MakesUploads;

    private FakeUploadStorage $storage;

    protected function defineEnvironment($app): void
    {
        $this->configureUploads($app);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The disk stays an S3 one, which signs: the tests that store a file fake it
        $this->storage = new FakeUploadStorage($this->app->make(S3UploadStorage::class));
        $this->app->instance(UploadStorage::class, $this->storage);
        $this->app->forgetInstance(Uploads::class);
        Storage::fake('files');
        Storage::fake('public');
        Panneau::allowWithoutPolicies();
        Panneau::resources([ReportsResource::class]);
    }

    public function test_a_file_in_content_is_a_completed_upload(): void
    {
        Storage::fake('uploads');

        $data = $this->data(['name' => 'cover.png', 'content' => base64_encode(self::png())]);

        $upload = Upload::query()->sole();
        $this->assertSame([
            'id' => $upload->uid,
            'status' => 'completed',
            'name' => 'cover.png',
            'mime' => 'image/png',
            'size' => strlen(self::png()),
            'value' => ['upload' => $upload->uid],
        ], $data);
        $this->assertStringStartsWith('upl_', $upload->uid);
        $this->assertSame(UploadStatus::Completed, $upload->status);
        $this->assertSame('1', $upload->user_id);
        $this->assertSame('uploads', $upload->disk);
        $this->assertSame('panneau-uploads/'.$upload->uid, $upload->path);
        $this->assertSame(self::png(), Storage::disk('uploads')->get($upload->path));
    }

    public function test_content_can_be_a_data_url_or_base64_on_several_lines(): void
    {
        Storage::fake('uploads');

        $data = $this->data(['name' => 'cover.png', 'content' => 'data:image/png;base64,'.base64_encode(self::png())]);
        $this->assertSame('completed', $data['status']);
        $this->assertSame(self::png(), Storage::disk('uploads')->get('panneau-uploads/'.$data['id']));

        $data = $this->data(['name' => 'terms.pdf', 'content' => chunk_split(base64_encode(self::PDF), 20)]);
        $this->assertSame('application/pdf', $data['mime']);
        $this->assertSame(self::PDF, Storage::disk('uploads')->get('panneau-uploads/'.$data['id']));
    }

    public function test_content_that_is_not_base64_is_an_error(): void
    {
        Storage::fake('uploads');

        foreach (['not base64!', 'data:image/png,'.self::PDF, 'data:image/png;base64,', '===='] as $content) {
            $result = $this->callTool(['name' => 'cover.png', 'content' => $content]);

            $this->assertErrors(['content'], $result);
            $this->assertSame(['`content` is not a file in base64.'], $result->problem['errors']['content']);
        }
        $this->assertSame(0, Upload::query()->count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_content_over_the_limit_is_an_error(): void
    {
        Storage::fake('uploads');
        $this->app['config']->set('panneau.agents.uploads.max_content_size', 10);

        $this->assertSame('completed', $this->data(['name' => 'a.txt', 'content' => base64_encode(str_repeat('a', 10))])['status']);
        // Decoded, then far over the limit, which isn't decoded
        foreach ([11, 5000] as $size) {
            $result = $this->callTool(['name' => 'a.txt', 'content' => base64_encode(str_repeat('a', $size))]);

            $this->assertErrors(['content'], $result);
            $this->assertSame(
                ['A file in `content` can\'t be larger than 10 bytes: send its `name` only, then the file to the `url` you get.'],
                $result->problem['errors']['content'],
            );
        }
        $this->assertSame(1, Upload::query()->count());
        $this->assertCount(1, Storage::disk('uploads')->allFiles());
    }

    public function test_the_limit_of_content_is_never_over_the_largest_upload(): void
    {
        Storage::fake('uploads');
        $this->app['config']->set('panneau.uploads.max_size', 20);

        $this->assertStringContainsString('up to 20 bytes', $this->app->make(UploadFile::class)->description());
        $this->assertErrors(['content'], $this->callTool(['name' => 'a.txt', 'content' => base64_encode(str_repeat('a', 21))]));
        $this->assertSame('completed', $this->data(['name' => 'a.txt', 'content' => base64_encode(str_repeat('a', 20))])['status']);
    }

    public function test_the_type_is_the_one_of_the_contents_not_of_the_name(): void
    {
        Storage::fake('uploads');

        $this->assertSame('image/png', $this->data(['name' => 'notes.pdf', 'content' => base64_encode(self::png())])['mime']);
        $this->assertSame('application/pdf', $this->data(['name' => 'photo.png', 'content' => base64_encode(self::PDF)])['mime']);
        $this->assertSame('text/html', $this->data(['name' => 'photo.png', 'content' => base64_encode('<!DOCTYPE html><html><body><script>alert(1)</script></body></html>')])['mime']);
        // Plain text: the extension tells which
        $this->assertSame('text/csv', $this->data(['name' => 'people.csv', 'content' => base64_encode("name,email\nAda,ada@example.com\n")])['mime']);
    }

    public function test_a_name_alone_starts_an_upload_with_a_presigned_put(): void
    {
        $data = $this->data(['name' => 'video.mp4']);

        $upload = Upload::query()->sole();
        $this->assertSame(['id', 'status', 'name', 'method', 'url', 'headers', 'expiresIn'], array_keys($data));
        $this->assertSame($upload->uid, $data['id']);
        $this->assertSame('open', $data['status']);
        $this->assertSame('video.mp4', $data['name']);
        $this->assertSame('PUT', $data['method']);
        $this->assertStringStartsWith('https://storage.test/panneau-test/panneau-uploads/'.$upload->uid.'?', $data['url']);
        $this->assertStringContainsString('X-Amz-Signature=', $data['url']);
        $this->assertStringContainsString('X-Amz-Expires=900', $data['url']);
        // A single `PUT`: no multipart upload
        $this->assertStringNotContainsString('uploadId', $data['url']);
        $this->assertSame(['Content-Type' => 'video/mp4'], $data['headers']);
        $this->assertSame(900, $data['expiresIn']);
        $this->assertSame(UploadStatus::Open, $upload->status);
        $this->assertSame('video.mp4', $upload->name);
        $this->assertSame('1', $upload->user_id);

        $this->assertSame(['Content-Type' => 'application/octet-stream'], $this->data(['name' => 'unknown'])['headers']);
    }

    public function test_the_upload_alone_completes_it_once_the_file_is_on_the_disk(): void
    {
        $started = $this->data(['name' => 'video.mp4']);
        $upload = Upload::query()->sole();

        // An open upload is no value yet
        $this->assertNull($this->app->make(Uploads::class)->resolve(['upload' => $started['id']], $this->user()));
        $result = $this->callTool(['upload' => $started['id']]);
        $this->assertErrors(['upload'], $result);
        $this->assertSame(['The file is not on the storage yet: send it to the `url` of the upload first.'], $result->problem['errors']['upload']);
        $this->assertSame(UploadStatus::Open, $upload->refresh()->status);

        $this->storage->files[$upload->path] = ['size' => 2048, 'mime' => 'video/mp4'];
        $completed = [
            'id' => $upload->uid,
            'status' => 'completed',
            'name' => 'video.mp4',
            'mime' => 'video/mp4',
            'size' => 2048,
            'value' => ['upload' => $upload->uid],
        ];
        $this->assertSame($completed, $this->data(['upload' => $started['id']]));
        $this->assertSame(UploadStatus::Completed, $upload->refresh()->status);
        $this->assertNotNull($this->app->make(Uploads::class)->resolve($completed['value'], $this->user()));

        // Twice: the same upload, even when the disk changed since
        $this->storage->files[$upload->path] = ['size' => 1, 'mime' => 'text/plain'];
        $this->assertSame($completed, $this->data(['upload' => $started['id']]));
    }

    public function test_a_file_too_large_is_removed_when_the_upload_completes(): void
    {
        $this->app['config']->set('panneau.uploads.max_size', 1000);
        $started = $this->data(['name' => 'video.mp4']);
        $upload = Upload::query()->sole();
        $this->storage->files[$upload->path] = ['size' => 1001, 'mime' => 'video/mp4'];

        $result = $this->callTool(['upload' => $started['id']]);

        $this->assertErrors(['upload'], $result);
        $this->assertSame(['Files can\'t be larger than 1000 bytes: the upload was removed.'], $result->problem['errors']['upload']);
        $this->assertSame(0, Upload::query()->count());
        $this->assertSame([['path' => $upload->path, 'uploadId' => null]], $this->storage->deleted);
        $this->assertSame(404, $this->callTool(['upload' => $started['id']])->status());
    }

    public function test_the_arguments_that_do_not_go_together(): void
    {
        $started = $this->data(['name' => 'video.mp4']);

        $result = $this->callTool(['upload' => $started['id'], 'name' => 'other.mp4']);
        $this->assertErrors(['name'], $result);
        $this->assertSame(['`name` starts an upload: completing one takes its `upload` alone.'], $result->problem['errors']['name']);
        $this->assertErrors(['content'], $this->callTool(['upload' => $started['id'], 'content' => base64_encode('a')]));
        $this->assertErrors(['name', 'content'], $this->callTool(['upload' => $started['id'], 'name' => 'a.txt', 'content' => base64_encode('a')]));
        $this->assertErrors(['name'], $this->callTool([]));
        $this->assertErrors(['name'], $this->callTool(['content' => base64_encode('a')]));
        $this->assertErrors(['name'], $this->callTool(['name' => str_repeat('a', 256)]));
        $this->assertErrors(['url'], $this->callTool(['name' => 'a.png', 'url' => 'https://example.com/a.png']));
        $this->assertSame(1, Upload::query()->count());
    }

    public function test_a_user_the_gate_denies_uploads_nothing(): void
    {
        $started = $this->data(['name' => 'video.mp4']);
        Storage::fake('uploads');
        Gate::define('uploadPanneauFiles', fn ($user): bool => false);

        $this->assertSame(403, $this->callTool(['name' => 'cover.png', 'content' => base64_encode(self::png())])->status());
        $this->assertSame(403, $this->callTool(['name' => 'video.mp4'])->status());
        $this->assertSame(403, $this->callTool(['upload' => $started['id']])->status());
        $this->assertSame(1, Upload::query()->count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_without_the_gate_uploads_are_denied_unless_policies_are_off(): void
    {
        Storage::fake('uploads');
        Panneau::allowWithoutPolicies(false);

        $this->assertSame(403, $this->callTool(['name' => 'cover.png', 'content' => base64_encode(self::png())])->status());

        Gate::define('uploadPanneauFiles', fn ($user): bool => $user->getAuthIdentifier() === 1);
        $this->assertSame('completed', $this->data(['name' => 'cover.png', 'content' => base64_encode(self::png())])['status']);
    }

    public function test_the_upload_of_another_user_is_not_found(): void
    {
        $other = $this->app->make(Uploads::class)->start(new GenericUser(['id' => 2]), 'video.mp4');
        $this->storage->files[$other->path] = ['size' => 10, 'mime' => 'video/mp4'];

        $this->assertSame(404, $this->callTool(['upload' => $other->uid])->status());
        $this->assertSame(404, $this->callTool(['upload' => 'upl_unknown'])->status());
        $this->assertSame(UploadStatus::Open, $other->refresh()->status);
    }

    public function test_save_item_takes_the_value_as_a_file_field(): void
    {
        Storage::fake('uploads');
        $terms = $this->data(['name' => 'Terms 2026.pdf', 'content' => base64_encode(self::PDF)]);
        $cover = $this->data(['name' => 'cover.png', 'content' => base64_encode(self::png())]);

        $result = $this->app->make(SaveItem::class)->handle($this->toolCall([
            'resource' => 'reports',
            'values' => ['title' => 'Annual', 'terms' => $terms['value'], 'attachments' => [$cover['value']]],
        ]));

        $this->assertFalse($result->isError(), (string) json_encode($result->problem));
        $this->assertSame('Terms 2026.pdf', $result->data['terms']['name'] ?? null);
        $this->assertSame('application/pdf', $result->data['terms']['mime'] ?? null);
        $report = Report::query()->sole();
        $this->assertSame('files', $report->terms['disk']);
        $this->assertSame(self::PDF, Storage::disk('files')->get($report->terms['path']));
        $this->assertSame(self::png(), Storage::disk('files')->get($report->attachments[0]['path']));

        // `accept` is checked on the type detected in the contents: a PNG named as a PDF
        $fake = $this->data(['name' => 'terms.pdf', 'content' => base64_encode(self::png())]);
        $refused = $this->app->make(SaveItem::class)->handle($this->toolCall(['resource' => 'reports', 'values' => ['title' => 'Fake', 'terms' => $fake['value']]]));
        $this->assertErrors(['terms'], $refused);
    }

    public function test_only_a_completed_upload_dispatches_the_event(): void
    {
        Event::fake([AgentToolCalled::class]);

        $started = $this->data(['name' => 'video.mp4']);
        $this->callTool(['upload' => $started['id']]);
        Storage::fake('uploads');
        $this->callTool(['name' => 'cover.png', 'content' => 'not base64!']);
        Event::assertNotDispatched(AgentToolCalled::class);

        $this->data(['name' => 'cover.png', 'content' => base64_encode(self::png())]);
        Event::assertDispatchedTimes(AgentToolCalled::class, 1);
        Event::assertDispatched(AgentToolCalled::class, fn (AgentToolCalled $event): bool => $event->tool === 'upload_file'
            && $event->user?->getAuthIdentifier() === 1
            && $event->resource === null
            && $event->items === []);

        $this->storage->files['panneau-uploads/'.$started['id']] = ['size' => 10, 'mime' => 'video/mp4'];
        $this->data(['upload' => $started['id']]);
        Event::assertDispatchedTimes(AgentToolCalled::class, 2);
    }

    public function test_the_tool_writes_and_describes_both_ways(): void
    {
        $tool = $this->app->make(UploadFile::class);

        $this->assertSame('upload_file', $tool->name());
        $this->assertFalse($tool->readOnly());
        $this->assertFalse($tool->destructive());
        $this->assertSame(['name', 'content', 'upload'], array_keys($tool->inputSchema()['properties']));
        $this->assertStringContainsString('up to 5242880 bytes', $tool->description());
        $this->assertStringContainsString('HTTP `PUT`', $tool->description());
    }

    private function user(): GenericUser
    {
        return new GenericUser(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function toolCall(array $arguments): ToolCall
    {
        $user = $this->user();
        $request = Request::create('/');
        $request->setUserResolver(fn (): GenericUser => $user);

        return new ToolCall($user, $arguments, $request);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(array $arguments): ToolResult
    {
        return $this->app->make(UploadFile::class)->handle($this->toolCall($arguments));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function data(array $arguments): array
    {
        $result = $this->callTool($arguments);
        $this->assertFalse($result->isError(), (string) json_encode($result->problem));

        return $result->data ?? [];
    }

    /**
     * @param  list<string>  $arguments
     */
    private function assertErrors(array $arguments, ToolResult $result): void
    {
        $this->assertSame(422, $result->status(), (string) json_encode($result->problem));
        $this->assertSame($arguments, array_keys($result->problem['errors'] ?? []));
    }
}
