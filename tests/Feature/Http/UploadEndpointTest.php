<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Panneau\Contracts\UploadStorage;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Upload;
use Panneau\Uploads\Uploads;
use Panneau\Uploads\UploadStatus;

class UploadEndpointTest extends HttpTestCase
{
    use ValidatesProtocol;

    private FakeUploadStorage $storage;

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
        $app['config']->set('panneau.uploads', [
            'enabled' => true,
            'disk' => 'uploads',
            'path' => 'panneau-uploads',
            'url_ttl' => 900,
            'lifetime' => 3600,
            'max_size' => 1000,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new FakeUploadStorage($this->app->make(S3UploadStorage::class));
        $this->app->instance(UploadStorage::class, $this->storage);
        // Registering the routes made the service with the real storage.
        $this->app->forgetInstance(Uploads::class);
        Gate::define('uploadPanneauFiles', fn ($user): bool => $user->getAuthIdentifier() !== 2);
    }

    /**
     * @return array{url: string, key: string, upload: string}
     */
    private function start(string $method = 'POST'): array
    {
        $response = $this->postJson(self::API.'/uploads/sign', ['method' => $method, 'key' => 'video.mp4'])->assertOk();

        /** @var array{url: string, key: string, upload: string} */
        return $response->json();
    }

    public function test_the_definition_announces_presigned_uploads(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response->assertJsonPath('uploads', ['mode' => 'presigned']);
    }

    public function test_the_first_request_of_a_file_starts_an_upload_with_its_own_key(): void
    {
        $signed = $this->start();

        $this->assertMatchesRegularExpression('/^upl_[0-9a-z]{26}$/', $signed['upload']);
        $this->assertSame('panneau-uploads/'.$signed['upload'], $signed['key']);
        $this->assertStringStartsWith('https://storage.test/panneau-test/panneau-uploads/'.$signed['upload'].'?uploads', $signed['url']);
        $this->assertStringContainsString('X-Amz-Signature=', $signed['url']);
        $this->assertStringContainsString('X-Amz-Expires=900', $signed['url']);
        $this->assertDatabaseHas('panneau_uploads', ['uid' => $signed['upload'], 'user_id' => '1', 'status' => 'open']);

        // A single PutObject starts an upload too.
        $put = $this->start('PUT');
        $this->assertNotSame($signed['upload'], $put['upload']);
    }

    public function test_later_requests_are_signed_only_for_the_users_open_uploads(): void
    {
        $signed = $this->start();
        $part = fn (array $overrides) => $this->postJson(self::API.'/uploads/sign', $overrides + [
            'method' => 'PUT', 'key' => $signed['key'], 'uploadId' => 'multipart-1', 'partNumber' => 2,
        ]);

        $url = $part([])->assertOk()->json('url');
        $this->assertIsString($url);
        $this->assertStringContainsString('partNumber=2', $url);
        $this->assertStringContainsString('uploadId=multipart-1', $url);
        $this->assertDatabaseHas('panneau_uploads', ['uid' => $signed['upload'], 'provider_upload_id' => 'multipart-1']);

        // Another multipart upload on the same key, an unknown key, a key outside the uploads.
        $part(['uploadId' => 'multipart-2'])->assertNotFound();
        $part(['key' => 'panneau-uploads/upl_unknown'])->assertNotFound();
        $part(['key' => 'secrets/'.$signed['upload']])->assertNotFound();

        // Another user.
        $this->actingAs(new GenericUser(['id' => 3, 'name' => 'Grace']));
        $part([])->assertNotFound();
    }

    public function test_it_refuses_requests_that_are_no_s3_operation_and_users_the_gate_denies(): void
    {
        $this->postJson(self::API.'/uploads/sign', ['method' => 'GET', 'key' => 'video.mp4'])
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', 'No S3 request has this method, upload id and part number.');

        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Linus']));
        $this->postJson(self::API.'/uploads/sign', ['method' => 'POST', 'key' => 'video.mp4'])->assertForbidden();
    }

    public function test_completing_an_upload_checks_its_file_and_gives_the_upload(): void
    {
        $signed = $this->start();
        $complete = fn () => $this->postJson(self::API.'/uploads/'.$signed['upload'].'/complete', ['name' => 'video.mp4']);

        $complete()->assertStatus(422)->assertJsonPath('errors.upload.0', 'Complete the upload on the storage first.');

        $this->storage->files[$signed['key']] = ['size' => 800, 'mime' => 'video/mp4'];
        $complete()->assertOk()->assertExactJson(['data' => [
            'id' => $signed['upload'],
            'key' => $signed['key'],
            'name' => 'video.mp4',
            'mime' => 'video/mp4',
            'size' => 800,
        ]]);
        // Completing again gives the same upload; a completed upload signs nothing more.
        $complete()->assertOk()->assertJsonPath('data.size', 800);
        $this->postJson(self::API.'/uploads/sign', ['method' => 'PUT', 'key' => $signed['key'], 'uploadId' => 'multipart-1', 'partNumber' => 1])
            ->assertNotFound();

        $this->postJson(self::API.'/uploads/upl_unknown/complete')->assertNotFound();
        $this->actingAs(new GenericUser(['id' => 3, 'name' => 'Grace']));
        $complete()->assertNotFound();
    }

    public function test_a_file_larger_than_the_limit_is_refused_and_removed(): void
    {
        $signed = $this->start();
        $this->storage->files[$signed['key']] = ['size' => 2000, 'mime' => 'video/mp4'];

        $this->postJson(self::API.'/uploads/'.$signed['upload'].'/complete')
            ->assertStatus(422)
            ->assertJsonPath('errors.upload.0', 'Files can\'t be larger than 1000 bytes.');
        $this->assertArrayNotHasKey($signed['key'], $this->storage->files);
        $this->assertDatabaseMissing('panneau_uploads', ['uid' => $signed['upload']]);
    }

    public function test_values_reference_completed_uploads_of_their_user(): void
    {
        $signed = $this->start();
        $uploads = $this->app->make(Uploads::class);
        $user = new GenericUser(['id' => 1]);

        $this->assertNull($uploads->resolve(['upload' => $signed['upload']], $user), 'An open upload is not a file yet.');

        $this->storage->files[$signed['key']] = ['size' => 10, 'mime' => 'text/csv'];
        $this->postJson(self::API.'/uploads/'.$signed['upload'].'/complete')->assertOk();

        $this->assertSame($signed['upload'], $uploads->resolve(['upload' => $signed['upload']], $user)?->uid);
        $this->assertNull($uploads->resolve(['upload' => $signed['upload']], new GenericUser(['id' => 3])));
        $this->assertNull($uploads->resolve('upl_'.$signed['upload'], $user));
        $this->assertNull($uploads->resolve(['upload' => $signed['upload']], null));
    }

    public function test_the_prune_command_removes_expired_uploads_and_aborts_their_multipart_uploads(): void
    {
        $open = $this->start();
        Upload::query()->where('uid', $open['upload'])->update(['provider_upload_id' => 'multipart-1']);
        $kept = $this->start();

        Carbon::setTestNow(Carbon::now()->addHours(2));
        $kept = Upload::query()->where('uid', $kept['upload'])->firstOrFail();
        $kept->forceFill(['status' => UploadStatus::Completed, 'expires_at' => Carbon::now()->addHour()])->save();

        $this->artisan('panneau:prune-uploads')->expectsOutputToContain('Removed 1 expired upload.')->assertSuccessful();

        $this->assertSame([['path' => $open['key'], 'uploadId' => 'multipart-1']], $this->storage->deleted);
        $this->assertDatabaseMissing('panneau_uploads', ['uid' => $open['upload']]);
        $this->assertDatabaseHas('panneau_uploads', ['uid' => $kept->uid]);
        Carbon::setTestNow();
    }
}
