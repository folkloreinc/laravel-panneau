<?php

namespace Panneau\Tests\Unit\Fields;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Panneau\Fields\File;
use Panneau\Forms\Form;
use Panneau\Forms\ValidationContext;
use Panneau\Tests\TestCase;
use Panneau\Uploads\Upload;

class FileTest extends TestCase
{
    public function test_definition(): void
    {
        $this->assertSame(['name' => 'terms', 'type' => 'file'], File::make('terms')->toDefinition());
        $this->assertSame([
            'name' => 'attachments',
            'type' => 'file',
            'rules' => [
                ['rule' => 'required'],
                ['rule' => 'accept', 'value' => ['application/pdf', 'image/*']],
                ['rule' => 'maxSize', 'value' => 5000000],
            ],
            'multiple' => true,
            'accept' => ['application/pdf', 'image/*'],
        ], File::make('attachments')
            ->multiple()
            ->required()
            ->accept(['application/pdf', 'image/*'])
            ->maxSize(5_000_000)
            ->disk('s3')
            ->directory('/attachments/')
            ->visibility('public')
            ->toDefinition());
    }

    public function test_the_disk_defaults_to_the_apps_default_disk(): void
    {
        $this->app['config']->set('filesystems.default', 'local');

        $this->assertSame('local', File::make('terms')->getDisk());
        $this->assertSame('s3', File::make('terms')->disk('s3')->getDisk());
    }

    public function test_the_visibility_is_public_or_private(): void
    {
        $this->expectException(LogicException::class);
        File::make('terms')->visibility('shared');
    }

    public function test_a_file_field_cant_be_localized(): void
    {
        $this->assertFalse(File::make('terms')->localized(false)->isLocalized());

        $this->expectException(LogicException::class);
        File::make('terms')->localized();
    }

    public function test_items_give_stored_files(): void
    {
        Storage::fake('public');
        $stored = ['disk' => 'public', 'path' => 'files/01/terms.pdf', 'name' => 'Terms.pdf', 'mime' => 'application/pdf', 'size' => 12];
        $file = File::make('terms')->visibility('public');
        $expected = ['name' => 'Terms.pdf', 'url' => Storage::disk('public')->url('files/01/terms.pdf'), 'mime' => 'application/pdf', 'size' => 12];

        $this->assertSame($expected, $file->serialize($stored));
        $this->assertSame($expected, $file->serialize((string) json_encode($stored)), 'A JSON column without a cast.');
        $this->assertNull($file->serialize(null));
        $this->assertSame([$expected], File::make('files')->multiple()->visibility('public')->serialize([$stored, 'not a file', ['path' => '']]));
        $this->assertSame([], File::make('files')->multiple()->serialize(null));
        // A value without a name is named after its path.
        $this->assertSame('terms.pdf', $file->serialize(['disk' => 'public', 'path' => 'files/01/terms.pdf'])['name'] ?? null);
    }

    public function test_urls_of_private_files_are_temporary(): void
    {
        Carbon::setTestNow('2026-10-21 12:00:00');
        Storage::fake('files')->buildTemporaryUrlsUsing(
            fn (string $path, \DateTimeInterface $expiration): string => 'https://files.test/'.$path.'?expires='.$expiration->getTimestamp(),
        );
        $this->app['config']->set('panneau.files.url_ttl', 120);

        $this->assertSame(
            'https://files.test/a/b.pdf?expires='.Carbon::now()->addSeconds(120)->getTimestamp(),
            File::make('terms')->url(['disk' => 'files', 'path' => 'a/b.pdf']),
        );
        Carbon::setTestNow();
    }

    public function test_the_url_is_null_when_the_disk_gives_none(): void
    {
        Storage::set('plain', Storage::createLocalDriver(['root' => storage_path('app/plain')]));

        $this->assertNull(File::make('terms')->url(['disk' => 'plain', 'path' => 'a/b.pdf']));
    }

    public function test_values_are_validated_with_the_uploads_of_the_context(): void
    {
        $uploads = [
            'upl_pdf' => new Upload(['uid' => 'upl_pdf', 'mime' => 'application/pdf', 'size' => 100]),
            'upl_text' => new Upload(['uid' => 'upl_text', 'mime' => 'text/plain', 'size' => 100]),
        ];
        // Without an item, as for the parameters of an action.
        $context = new ValidationContext(uploads: fn (mixed $value): ?Upload => is_array($value) && is_string($value['upload'] ?? null) ? $uploads[$value['upload']] ?? null : null);
        $fields = [File::make('file')->accept('application/pdf')->maxSize(500)];
        $errors = function (array $input, ?ValidationContext $with = null) use ($fields, $context): array {
            try {
                Form::validate($this->app['validator'], $fields, $input, $with ?? $context);
            } catch (ValidationException $exception) {
                return $exception->errors();
            }

            return [];
        };

        $this->assertSame([], $errors(['file' => ['upload' => 'upl_pdf']]));
        $this->assertSame(['file' => ['The file must be a file of type: application/pdf.']], $errors(['file' => ['upload' => 'upl_text', 'mime' => 'application/pdf']]));
        $this->assertSame(['file' => ['The upload of the file does not exist.']], $errors(['file' => ['upload' => 'upl_missing']]));
        $this->assertSame(['file' => ['The file is not a file of this item.']], $errors(['file' => ['name' => 'terms.pdf', 'url' => 'https://files.test/terms.pdf']]));
        $this->assertSame(
            ['file' => ['The upload of the file does not exist.']],
            $errors(['file' => ['upload' => 'upl_pdf']], new ValidationContext),
            'Without uploads, no upload resolves.',
        );
    }
}
