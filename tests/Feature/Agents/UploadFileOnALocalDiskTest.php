<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Panneau\Agents\Tools\SaveItem;
use Panneau\Agents\Tools\UploadFile;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Feature\Http\Concerns\MakesUploads;
use Panneau\Tests\Feature\Http\HttpTestCase;
use Panneau\Uploads\Upload;
use Panneau\Uploads\Uploads;
use Panneau\Uploads\UploadStatus;
use TestApp\Models\Report;
use TestApp\Panneau\Resources\ReportsResource;

/**
 * `upload_file` in a project without direct uploads, on a local disk that signs nothing: the MCP
 * server alone takes files in `content`, and values reference them.
 */
class UploadFileOnALocalDiskTest extends HttpTestCase
{
    use MakesUploads;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('panneau.agents.mcp.enabled', true);
        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('filesystems.disks.files', ['driver' => 'local', 'root' => storage_path('app/files')]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('files');
        Storage::fake('public');
        Panneau::allowWithoutPolicies();
        Panneau::resources([ReportsResource::class]);
    }

    public function test_a_file_in_content_is_stored_on_the_default_disk(): void
    {
        $this->assertFalse($this->app->make(Uploads::class)->enabled());

        $data = $this->data(['name' => 'terms.pdf', 'content' => base64_encode(self::PDF)]);

        $upload = Upload::query()->sole();
        $this->assertSame(['upload' => $upload->uid], $data['value']);
        $this->assertSame('application/pdf', $data['mime']);
        $this->assertSame(UploadStatus::Completed, $upload->status);
        $this->assertSame('local', $upload->disk);
        $this->assertSame(self::PDF, Storage::disk('local')->get('panneau-uploads/'.$upload->uid));
    }

    public function test_a_name_alone_is_refused_since_the_disk_signs_nothing(): void
    {
        $result = $this->callTool(['name' => 'video.mp4']);

        $this->assertSame(422, $result->status(), (string) json_encode($result->problem));
        $this->assertSame(['content' => ['This site takes a file as `content` only, in base64, up to 5242880 bytes.']], $result->problem['errors'] ?? null);
        $this->assertSame(0, Upload::query()->count());

        $description = $this->app->make(UploadFile::class)->description();
        $this->assertStringContainsString('This site takes no larger file from an agent.', $description);
        $this->assertStringNotContainsString('PUT', $description);
    }

    public function test_content_over_the_limit_names_no_url(): void
    {
        $this->app['config']->set('panneau.agents.uploads.max_content_size', 10);

        $result = $this->callTool(['name' => 'a.txt', 'content' => base64_encode(str_repeat('a', 11))]);

        $this->assertSame(['content' => ['A file in `content` can\'t be larger than 10 bytes.']], $result->problem['errors'] ?? null);
    }

    public function test_save_item_takes_the_value_as_a_file_field(): void
    {
        $terms = $this->data(['name' => 'terms.pdf', 'content' => base64_encode(self::PDF)]);

        $result = $this->app->make(SaveItem::class)->handle($this->toolCall(['resource' => 'reports', 'values' => ['title' => 'Annual', 'terms' => $terms['value']]]));

        $this->assertFalse($result->isError(), (string) json_encode($result->problem));
        $this->assertSame('terms.pdf', $result->data['terms']['name'] ?? null);
        $report = Report::query()->sole();
        $this->assertSame('files', $report->terms['disk']);
        $this->assertSame(self::PDF, Storage::disk('files')->get($report->terms['path']));
    }

    public function test_the_upload_of_another_user_is_no_value(): void
    {
        $terms = $this->data(['name' => 'terms.pdf', 'content' => base64_encode(self::PDF)]);

        $result = $this->app->make(SaveItem::class)->handle($this->toolCall(['resource' => 'reports', 'values' => ['title' => 'Annual', 'terms' => $terms['value']]], 2));

        $this->assertSame(422, $result->status(), (string) json_encode($result->problem));
        $this->assertSame(['terms'], array_keys($result->problem['errors'] ?? []));
        $this->assertSame(0, Report::query()->count());
    }

    public function test_without_the_server_nor_direct_uploads_no_value_is_an_upload(): void
    {
        $terms = $this->data(['name' => 'terms.pdf', 'content' => base64_encode(self::PDF)]);
        $this->app['config']->set('panneau.agents.mcp.enabled', false);

        $this->assertFalse($this->app->make(Uploads::class)->accepted());
        $this->assertNull($this->app->make(Uploads::class)->resolve($terms['value'], new GenericUser(['id' => 1])));
        $result = $this->app->make(SaveItem::class)->handle($this->toolCall(['resource' => 'reports', 'values' => ['title' => 'Annual', 'terms' => $terms['value']]]));
        $this->assertSame(422, $result->status(), (string) json_encode($result->problem));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function toolCall(array $arguments, int $user = 1): ToolCall
    {
        $user = new GenericUser(['id' => $user, 'name' => 'Ada', 'email' => 'ada@example.com']);
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
}
