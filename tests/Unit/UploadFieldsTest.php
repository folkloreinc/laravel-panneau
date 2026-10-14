<?php

namespace Panneau\Tests\Unit;

use Panneau\Fields\AudioUpload;
use Panneau\Fields\DocumentUpload;
use Panneau\Fields\ImageUpload;
use Panneau\Fields\VideoUpload;
use Panneau\Tests\TestCase;

class UploadFieldsTest extends TestCase
{
    public function testUploadFieldsUseTheUploadComponent()
    {
        $this->assertEquals('image_upload', ImageUpload::make('image')->component());
        $this->assertEquals('video_upload', VideoUpload::make('video')->component());
        $this->assertEquals('audio_upload', AudioUpload::make('audio')->component());
        $this->assertEquals('document_upload', DocumentUpload::make('document')->component());
    }
}
