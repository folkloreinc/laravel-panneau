<?php

namespace Panneau\Tests\Feature\Http\Concerns;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Panneau\Contracts\UploadStorage;
use Panneau\Tests\Feature\Http\FakeUploadStorage;
use Panneau\Uploads\S3UploadStorage;
use Panneau\Uploads\Uploads;
use Panneau\Uploads\UploadStatus;

/**
 * Direct uploads on a fake `uploads` disk, and files of a few types.
 */
trait MakesUploads
{
    protected const PDF = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    /**
     * @param  Application  $app
     */
    protected function configureUploads($app): void
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

    protected function fakeUploads(): void
    {
        $this->app->instance(UploadStorage::class, new FakeUploadStorage($this->app->make(S3UploadStorage::class)));
        $this->app->forgetInstance(Uploads::class);
        Storage::fake('uploads');
    }

    /**
     * A completed upload of the user, with its file on the uploads disk.
     */
    protected function upload(string $name, string $contents, string $mime, int $user = 1): string
    {
        $upload = $this->app->make(Uploads::class)->start(new GenericUser(['id' => $user]));
        Storage::disk('uploads')->put($upload->path, $contents);
        $upload->forceFill([
            'status' => UploadStatus::Completed,
            'name' => $name,
            'mime' => $mime,
            'size' => strlen($contents),
        ])->save();

        return $upload->uid;
    }

    /**
     * A PNG image of one pixel.
     */
    protected static function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }
}
