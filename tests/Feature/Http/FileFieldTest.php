<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Panneau\Contracts\UploadStorage;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Uploads;
use Panneau\Uploads\UploadStatus;
use Symfony\Component\HttpFoundation\Response;
use TestApp\Models\Report;
use TestApp\Panneau\Resources\ReportsResource;

class FileFieldTest extends HttpTestCase
{
    use ValidatesProtocol;

    private const PDF = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.uploads', [
            'driver' => 's3',
            'key' => 'test',
            'secret' => 'test',
            'region' => 'us-east-1',
            'bucket' => 'panneau-test',
            'endpoint' => 'https://storage.test',
            'use_path_style_endpoint' => true,
        ]);
        $app['config']->set('filesystems.disks.files', ['driver' => 'local', 'root' => storage_path('app/files')]);
        $app['config']->set('panneau.uploads', [
            'enabled' => true,
            'disk' => 'uploads',
            'path' => 'panneau-uploads',
            'url_ttl' => 900,
            'lifetime' => 3600,
            'max_size' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(UploadStorage::class, new FakeUploadStorage($this->app->make(S3UploadStorage::class)));
        $this->app->forgetInstance(Uploads::class);
        Storage::fake('uploads');
        Storage::fake('files');
        Storage::fake('public');
        Panneau::resources([ReportsResource::class]);
        Panneau::allowWithoutPolicies();
        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An upload of the user, with its file on the uploads disk. A completed upload has the name,
     * MIME type and size that `POST uploads/{upload}/complete` records.
     */
    private function upload(string $name, string $contents, string $mime, int $user = 1, bool $completed = true): string
    {
        $upload = $this->app->make(Uploads::class)->start(new GenericUser(['id' => $user]));
        Storage::disk('uploads')->put($upload->path, $contents);
        if ($completed) {
            $upload->forceFill([
                'status' => UploadStatus::Completed,
                'name' => $name,
                'mime' => $mime,
                'size' => strlen($contents),
            ])->save();
        }

        return $upload->uid;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return TestResponse<Response>
     */
    private function create(array $values): TestResponse
    {
        return $this->postJson(self::API.'/resources/reports', $values + ['title' => 'Annual report']);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return TestResponse<Response>
     */
    private function update(Report $report, array $values): TestResponse
    {
        return $this->patchJson(self::API.'/resources/reports/'.$report->id, $values);
    }

    private function report(TestResponse $response): Report
    {
        return Report::query()->findOrFail($response->json('data.id'));
    }

    /**
     * The files written on a fake disk.
     *
     * @return list<string>
     */
    private static function files(string $disk): array
    {
        return Storage::disk($disk)->allFiles();
    }

    private static function temporaryUrl(string $path, int $ttl = 3600): string
    {
        return url($path.'?expiration='.Carbon::now()->addSeconds($ttl)->getTimestamp());
    }

    public function test_the_definition_describes_file_fields(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $resources = collect($response->json('resources'));
        $fields = collect($resources->firstWhere('id', 'reports')['fields'])->keyBy('name');
        $this->assertSame([
            'name' => 'terms',
            'type' => 'file',
            'rules' => [
                ['rule' => 'accept', 'value' => ['application/pdf']],
                ['rule' => 'maxSize', 'value' => 10000, 'message' => 'The terms are too large.'],
            ],
            'accept' => ['application/pdf'],
        ], $fields['terms']);
        $this->assertSame([
            'name' => 'attachments',
            'type' => 'file',
            'rules' => [['rule' => 'max', 'value' => 3]],
            'multiple' => true,
        ], $fields['attachments']);
        // Storage settings stay on the server.
        $this->assertArrayNotHasKey('disk', $fields['cover']);
        $this->assertArrayNotHasKey('visibility', $fields['cover']);
    }

    public function test_create_copies_the_file_of_an_upload_and_stores_it_in_the_attribute(): void
    {
        $upload = $this->upload('Terms of use (2026).pdf', self::PDF, 'application/pdf');

        $response = $this->create(['terms' => ['upload' => $upload]])->assertCreated();

        $terms = $this->report($response)->terms;
        $this->assertIsArray($terms);
        $this->assertSame('files', $terms['disk']);
        $this->assertMatchesRegularExpression('#^terms/[0-9a-z]{26}/terms-of-use-2026\.pdf$#', $terms['path']);
        $this->assertSame(['Terms of use (2026).pdf', 'application/pdf', strlen(self::PDF)], [$terms['name'], $terms['mime'], $terms['size']]);
        $this->assertSame([$terms['path']], self::files('files'));
        $this->assertSame(self::PDF, Storage::disk('files')->get($terms['path']));
        // The upload keeps its file until it expires.
        $this->assertCount(1, self::files('uploads'));

        $response->assertJsonPath('data.terms', [
            'name' => 'Terms of use (2026).pdf',
            'url' => self::temporaryUrl($terms['path']),
            'mime' => 'application/pdf',
            'size' => strlen(self::PDF),
        ]);
        $this->assertMatchesComponent($response->json('data.terms'), 'StoredFile');
        $this->assertSame($response->json('data.terms'), $this->getJson(self::API.'/resources/reports/'.$response->json('data.id'))->json('data.terms'));
    }

    public function test_a_stored_file_sent_back_keeps_the_items_own_file(): void
    {
        $response = $this->create(['terms' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]]);
        $report = $this->report($response);
        $stored = $report->terms;
        $sent = $response->json('data.terms');
        $this->assertIsArray($sent);

        // Temporary URLs change: the file is found by its URL without the query string.
        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->update($report, ['title' => 'Renamed', 'terms' => $sent])->assertOk();
        $this->assertSame($stored, $report->refresh()->terms);
        $this->assertSame('Renamed', $report->title);

        // What the client says about the file is ignored: the item's own stored value is kept.
        $this->update($report, ['terms' => ['url' => null, 'disk' => 'public', 'path' => '../../.env', 'mime' => 'text/html', 'size' => 1] + $sent])->assertOk();
        $this->assertSame($stored, $report->refresh()->terms);
        $this->assertSame([$stored['path']], self::files('files'));

        // A file that is not the item's.
        $this->update($report, ['terms' => ['name' => 'other.pdf', 'url' => 'https://example.com/other.pdf']])
            ->assertStatus(422)
            ->assertJsonPath('errors.terms.0', 'The terms is not a file of this item.');
        $this->update($report, ['terms' => 'terms/secret.pdf'])
            ->assertStatus(422)
            ->assertJsonPath('errors.terms.0', 'The terms must be a file.');
        $this->assertSame($stored, $report->refresh()->terms);
    }

    public function test_replacing_a_file_deletes_the_previous_one_once_the_item_is_saved(): void
    {
        $report = $this->report($this->create(['terms' => ['upload' => $this->upload('old.pdf', self::PDF, 'application/pdf')]]));
        $old = $report->terms['path'];

        $response = $this->update($report, ['terms' => ['upload' => $this->upload('new.pdf', self::PDF.'%new', 'application/pdf')]])->assertOk();

        $new = $report->refresh()->terms;
        $this->assertSame('new.pdf', $new['name']);
        $this->assertNotSame($old, $new['path']);
        $this->assertSame([$new['path']], self::files('files'));
        $response->assertJsonPath('data.terms.name', 'new.pdf');
    }

    public function test_null_removes_the_file(): void
    {
        $report = $this->report($this->create(['terms' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]]));

        $this->update($report, ['terms' => null])->assertOk()->assertJsonPath('data.terms', null);

        $this->assertNull($report->refresh()->terms);
        $this->assertSame([], self::files('files'));
    }

    public function test_a_partial_update_without_the_field_keeps_its_file(): void
    {
        $report = $this->report($this->create(['terms' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]]));
        $stored = $report->terms;

        $this->update($report, ['title' => 'Renamed'])->assertOk();

        $this->assertSame($stored, $report->refresh()->terms);
        $this->assertSame([$stored['path']], self::files('files'));
    }

    public function test_a_multiple_field_keeps_replaces_and_removes_files_in_its_list(): void
    {
        $response = $this->create(['attachments' => [
            ['upload' => $this->upload('a.txt', 'A', 'text/plain')],
            ['upload' => $this->upload('b.txt', 'B', 'text/plain')],
        ]])->assertCreated();
        $report = $this->report($response);
        [$a, $b] = $report->attachments;
        $this->assertSame(['a.txt', 'b.txt'], array_column($report->attachments, 'name'));
        $this->assertStringStartsWith('files/', $a['path']);
        $this->assertCount(2, $response->json('data.attachments'));
        foreach ($response->json('data.attachments') as $file) {
            $this->assertMatchesComponent($file, 'StoredFile');
        }

        // Keep b, add c, drop a.
        $response = $this->update($report, ['attachments' => [
            $response->json('data.attachments.1'),
            ['upload' => $this->upload('c.txt', 'C', 'text/plain')],
        ]])->assertOk();

        $this->assertSame(['b.txt', 'c.txt'], array_column($report->refresh()->attachments, 'name'));
        $this->assertSame($b, $report->attachments[0]);
        $this->assertFalse(Storage::disk('files')->exists($a['path']));
        $this->assertSame('C', Storage::disk('files')->get($report->attachments[1]['path']));
        $response->assertJsonPath('data.attachments.*.name', ['b.txt', 'c.txt']);

        $this->update($report, ['attachments' => []])->assertOk()->assertJsonPath('data.attachments', []);
        $this->assertSame([], self::files('files'));
    }

    public function test_a_multiple_field_checks_each_entry_and_the_rules_of_the_list(): void
    {
        $upload = fn (string $name): array => ['upload' => $this->upload($name, 'x', 'text/plain')];

        $this->create(['attachments' => [$upload('a.txt'), null]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['attachments.1' => ['The attachments field is required.']]);
        $this->create(['attachments' => [$upload('a.txt'), $upload('b.txt'), $upload('c.txt'), $upload('d.txt')]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['attachments' => ['The attachments field must not have more than 3 items.']]);
        $this->create(['attachments' => $upload('a.txt')])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['attachments']]);
        $this->assertSame([], self::files('files'));
    }

    public function test_uploads_that_are_unknown_open_or_of_another_user_are_refused_on_their_path(): void
    {
        $other = $this->upload('other.txt', 'x', 'text/plain', user: 3);
        $open = $this->upload('open.pdf', self::PDF, 'application/pdf', completed: false);

        $response = $this->create([
            'terms' => ['upload' => 'upl_unknown'],
            'attachments' => [['upload' => $this->upload('mine.txt', 'x', 'text/plain')], ['upload' => $other]],
            'sections' => [['heading' => 'Intro'], ['heading' => 'Annex', 'file' => ['upload' => $open]]],
        ])->assertStatus(422);

        $this->assertEqualsCanonicalizing([
            'terms' => ['The upload of the terms does not exist.'],
            'attachments.1' => ['The upload of the attachments does not exist.'],
            'sections.1.file' => ['The upload of the file does not exist.'],
        ], $response->json('errors'));
        $this->assertSame(0, Report::query()->count());
        $this->assertSame([], self::files('files'));
    }

    public function test_an_upload_is_refused_when_uploads_are_disabled(): void
    {
        $upload = $this->upload('terms.pdf', self::PDF, 'application/pdf');
        $this->app['config']->set('panneau.uploads.enabled', false);

        $this->create(['terms' => ['upload' => $upload]])
            ->assertStatus(422)
            ->assertJsonPath('errors.terms.0', 'The upload of the terms does not exist.');
    }

    public function test_accept_and_max_size_are_checked_on_what_the_upload_recorded(): void
    {
        // The client's MIME type and size next to the upload are ignored.
        $text = $this->upload('terms.pdf', 'Not a PDF', 'text/plain');
        $this->create(['terms' => ['upload' => $text, 'mime' => 'application/pdf', 'size' => 9]])
            ->assertStatus(422)
            ->assertJsonPath('errors.terms', ['The terms must be a file of type: application/pdf.']);

        $large = $this->upload('terms.pdf', str_repeat('%', 10_001), 'application/pdf');
        $this->create(['terms' => ['upload' => $large, 'size' => 10]])
            ->assertStatus(422)
            ->assertJsonPath('errors.terms', ['The terms are too large.']);

        // `image/*` accepts any image.
        $png = $this->upload('cover.png', 'png', 'image/png');
        $this->create(['cover' => ['upload' => $png]])->assertCreated();
        $this->create(['cover' => ['upload' => $this->upload('cover.svg', '<svg/>', 'text/html')]])
            ->assertStatus(422)
            ->assertJsonPath('errors.cover', ['The cover must be a file of type: image/*.']);
    }

    public function test_a_file_in_multipart_is_stored_with_its_detected_type_and_size(): void
    {
        $response = $this->post(self::API.'/resources/reports', [
            'title' => 'Annual report',
            'terms' => UploadedFile::fake()->createWithContent('Terms.pdf', self::PDF),
            'attachments' => [UploadedFile::fake()->createWithContent('notes.txt', 'Some notes')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $report = $this->report($response);
        $this->assertSame(['Terms.pdf', 'application/pdf', strlen(self::PDF)], [$report->terms['name'], $report->terms['mime'], $report->terms['size']]);
        $this->assertMatchesRegularExpression('#^terms/[0-9a-z]{26}/terms\.pdf$#', $report->terms['path']);
        $this->assertSame(self::PDF, Storage::disk('files')->get($report->terms['path']));
        $this->assertSame(['notes.txt', 'text/plain', 10], [$report->attachments[0]['name'], $report->attachments[0]['mime'], $report->attachments[0]['size']]);
        $response->assertJsonPath('data.terms.mime', 'application/pdf');

        // A name and a client MIME type don't make a PDF: the type is detected from the content.
        $path = tempnam(sys_get_temp_dir(), 'panneau');
        file_put_contents($path, 'Not a PDF');
        $this->post(self::API.'/resources/reports', [
            'title' => 'Annual report',
            'terms' => new UploadedFile($path, 'terms.pdf', 'application/pdf', test: true),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.terms', ['The terms must be a file of type: application/pdf.']);
        $this->assertSame(1, Report::query()->count());
    }

    public function test_public_files_get_the_disks_url_and_private_files_a_temporary_url(): void
    {
        $response = $this->create([
            'terms' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')],
            'cover' => ['upload' => $this->upload('cover.png', 'png', 'image/png')],
        ])->assertCreated();
        $report = $this->report($response);

        $this->assertSame('public', $report->cover['disk']);
        $this->assertSame(Storage::disk('public')->url($report->cover['path']), $response->json('data.cover.url'));
        $this->assertSame('public', Storage::disk('public')->getVisibility($report->cover['path']));
        $this->assertSame(self::temporaryUrl($report->terms['path']), $response->json('data.terms.url'));
        $this->assertSame('private', Storage::disk('files')->getVisibility($report->terms['path']));

        $this->app['config']->set('panneau.files.url_ttl', 60);
        $this->getJson(self::API.'/resources/reports/'.$report->id)
            ->assertJsonPath('data.terms.url', self::temporaryUrl($report->terms['path'], 60));
    }

    public function test_files_in_blocks_are_kept_or_deleted_with_their_blocks(): void
    {
        $response = $this->create(['sections' => [
            ['heading' => 'Intro', 'file' => ['upload' => $this->upload('intro.txt', 'Intro', 'text/plain')]],
            ['heading' => 'Annex', 'file' => ['upload' => $this->upload('annex.txt', 'Annex', 'text/plain')]],
            ['heading' => 'Empty'],
        ]])->assertCreated();
        $report = $this->report($response);
        [$intro, $annex] = array_column($report->sections, 'file');
        $this->assertStringStartsWith('sections/', $intro['path']);
        $response->assertJsonPath('data.sections.1.file.name', 'annex.txt');
        $this->assertMatchesComponent($response->json('data.sections.1.file'), 'StoredFile');

        // The annex moves up, its file kept; the intro and its file are gone.
        $this->update($report, ['sections' => [
            ['heading' => 'Annex', 'file' => $response->json('data.sections.1.file')],
            ['heading' => 'Empty'],
        ]])->assertOk()->assertJsonPath('data.sections.0.file.name', 'annex.txt');

        $this->assertSame($annex, $report->refresh()->sections[0]['file']);
        $this->assertSame([$annex['path']], self::files('files'));

        $this->update($report, ['sections' => []])->assertOk();
        $this->assertSame([], self::files('files'));
    }

    public function test_a_failed_save_deletes_the_files_it_copied(): void
    {
        $this->create([
            'title' => 'Refused',
            'terms' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')],
            'attachments' => [UploadedFile::fake()->createWithContent('notes.txt', 'Some notes')],
        ])->assertStatus(422)->assertJsonPath('errors.title.0', 'This title is refused.');

        $this->assertSame(0, Report::query()->count());
        $this->assertSame([], self::files('files'));
    }

    public function test_a_failed_save_keeps_the_previous_files(): void
    {
        $report = $this->report($this->create(['terms' => ['upload' => $this->upload('terms.pdf', self::PDF, 'application/pdf')]]));
        $stored = $report->terms;

        // An invalid value: nothing is copied.
        $this->update($report, [
            'terms' => ['upload' => $this->upload('new.pdf', self::PDF, 'application/pdf')],
            'cover' => ['upload' => 'upl_unknown'],
        ])->assertStatus(422);
        $this->assertSame([$stored['path']], self::files('files'));

        // The updating hook fails after the new file was copied: the copy is deleted, the
        // previous file stays.
        $this->update($report, [
            'title' => 'Refused',
            'terms' => ['upload' => $this->upload('new.pdf', self::PDF, 'application/pdf')],
        ])->assertStatus(422)->assertJsonPath('errors.title.0', 'This title is refused.');

        $this->assertSame($stored, $report->refresh()->terms);
        $this->assertSame([$stored['path']], self::files('files'));
    }
}
