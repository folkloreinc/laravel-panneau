<?php

namespace Panneau\Tests\Unit;

use Panneau\Fields\AudioUpload;
use Panneau\Fields\DocumentUpload;
use Panneau\Fields\ImageUpload;
use Panneau\Fields\VideoUpload;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class UploadFieldsTest extends TestCase
{
    public static function uploadFields(): array
    {
        return [
            'image' => [ImageUpload::class, 'image_upload'],
            'video' => [VideoUpload::class, 'video_upload'],
            'audio' => [AudioUpload::class, 'audio_upload'],
            'document' => [DocumentUpload::class, 'document_upload'],
        ];
    }

    #[DataProvider('uploadFields')]
    public function test_upload_fields_load_and_use_their_upload_component(string $class, string $component)
    {
        $field = $class::make('file');

        $this->assertSame($component, $field->component());
    }
}
