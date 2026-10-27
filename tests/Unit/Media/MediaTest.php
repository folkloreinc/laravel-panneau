<?php

namespace Panneau\Tests\Unit\Media;

use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Support\Collection;
use LogicException;
use Panneau\Fields\Media;
use Panneau\Media\MediaTransform;
use Panneau\Media\MediaTypes;
use Panneau\Tests\TestCase;

class MediaTest extends TestCase
{
    public function test_the_definition_of_a_media_field(): void
    {
        $this->assertSame(['name' => 'image', 'type' => 'media'], Media::make('image')->toDefinition());
        $this->assertSame([
            'name' => 'gallery',
            'type' => 'media',
            'rules' => [['rule' => 'accept', 'value' => ['image/*']], ['rule' => 'maxSize', 'value' => 1000]],
            'multiple' => true,
            'accept' => ['image/*'],
            'resource' => 'images',
        ], Media::make('gallery')->resource('images')->multiple()->accept('image/*')->maxSize(1000)->toDefinition());
        $this->assertSame('images', Media::make('image')->resource('images')->relatedResource());
        $this->assertFalse(Media::make('image')->transforms(false)->keepsTransforms());
    }

    public function test_a_media_field_can_allow_several_libraries(): void
    {
        $field = Media::make('media')->resource(['images', 'documents', 'images']);
        $this->assertSame(['name' => 'media', 'type' => 'media', 'resource' => ['images', 'documents']], $field->toDefinition());
        $this->assertSame(['images', 'documents'], $field->libraries());
        $this->assertNull($field->relatedResource());
        $this->assertSame(['images', 'documents'], Media::make('media')->resource('images', 'documents')->libraries());
        $this->assertSame([], Media::make('media')->libraries());

        // Values keep the library of each media
        $this->assertSame(['id' => 'med_1', 'resource' => 'documents', 'transform' => ['rotate' => 90]], $field->serialize(['id' => 'med_1', 'resource' => 'documents', 'transform' => ['rotate' => 90]]));
    }

    public function test_a_media_field_that_keeps_ids_allows_one_library(): void
    {
        $this->assertSame('images', Media::make('cover')->transforms(false)->resource(['images'])->relatedResource());

        $this->expectException(LogicException::class);
        Media::make('cover')->transforms(false)->resource('images', 'documents');
    }

    public function test_a_media_field_that_keeps_ids_can_t_be_given_several_libraries_first(): void
    {
        $this->expectException(LogicException::class);
        Media::make('cover')->resource('images', 'documents')->transforms(false);
    }

    public function test_values_read_from_an_item_become_references(): void
    {
        $field = Media::make('cover');
        $this->assertSame(['id' => 'med_1'], $field->serialize('med_1'));
        $this->assertSame(['id' => '12'], $field->serialize(12));
        $this->assertSame(['id' => 'med_1', 'transform' => ['rotate' => 90]], $field->serialize(['id' => 'med_1', 'transform' => ['rotate' => 90, 'other' => 1]]));
        $this->assertSame(['id' => 'med_1', 'transform' => ['rotate' => 90]], $field->serialize('{"id":"med_1","transform":{"rotate":90}}'));
        $this->assertSame(['id' => 'med_1'], $field->serialize(new ArrayObject(['id' => 'med_1'])));
        $this->assertSame(['id' => 'med_1'], Media::make('cover')->transforms(false)->serialize(['id' => 'med_1', 'transform' => ['rotate' => 90]]));
        $this->assertNull($field->serialize(null));
        $item = (object) ['id' => 3];
        $this->assertSame($item, $field->serialize($item));

        $gallery = Media::make('gallery')->multiple();
        $this->assertSame([['id' => 'a'], ['id' => 'b']], $gallery->serialize(['a', ['id' => 'b'], ['no' => 'id']]));
        $this->assertSame([['id' => 'a']], $gallery->serialize(new Collection(['a'])));
        $this->assertSame([], $gallery->serialize(null));
    }

    public function test_media_types_of_mime_types(): void
    {
        $this->assertSame('image', MediaTypes::fromMime('image/svg+xml'));
        $this->assertSame('video', MediaTypes::fromMime('video/mp4'));
        $this->assertSame('audio', MediaTypes::fromMime('AUDIO/MPEG'));
        $this->assertSame('document', MediaTypes::fromMime('application/pdf'));
        $this->assertSame('document', MediaTypes::fromMime('text/csv; charset=utf-8'));
        $this->assertSame('font', MediaTypes::fromMime('font/woff2'));
        $this->assertSame('other', MediaTypes::fromMime('application/zip'));
        $this->assertSame('other', MediaTypes::fromMime(null));

        $this->assertSame(['image/*', 'video/*'], MediaTypes::accept(['image', 'video']));
        $this->assertNull(MediaTypes::accept(['image', 'other']));
        $this->assertNull(MediaTypes::accept([]));
        $this->assertSame(['image/png'], MediaTypes::accept(['image'], fn (string $type): array => ['image/png']));
    }

    public function test_transforms(): void
    {
        $transform = ['crop' => ['x' => 0, 'y' => 5, 'width' => 10, 'height' => 20], 'rotate' => 270, 'flipX' => true, 'flipY' => true, 'focalPoint' => ['x' => 0, 'y' => 1]];
        $this->assertNull(MediaTransform::error($transform));
        $this->assertNull(MediaTransform::error(null));
        $this->assertNull(MediaTransform::error([]));
        $this->assertSame($transform, MediaTransform::normalize($transform + ['scale' => 2]));
        $this->assertSame(['crop' => ['x' => 1, 'y' => 2, 'width' => 3, 'height' => 4]], MediaTransform::normalize(['crop' => ['x' => 1.0, 'y' => 2, 'width' => 3, 'height' => 4], 'rotate' => 0, 'flipX' => false]));
        $this->assertNull(MediaTransform::normalize(['rotate' => 0]));

        foreach ([
            'a transform',
            ['crop' => ['x' => -1, 'y' => 0, 'width' => 1, 'height' => 1]],
            ['crop' => ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 1]],
            ['crop' => ['x' => 0.5, 'y' => 0, 'width' => 1, 'height' => 1]],
            ['crop' => ['x' => 0, 'y' => 0, 'width' => 1]],
            ['rotate' => 45],
            ['rotate' => '90'],
            ['flipX' => 'yes'],
            ['focalPoint' => ['x' => 1.5, 'y' => 0]],
            ['focalPoint' => ['x' => 0.5]],
        ] as $invalid) {
            $this->assertNotNull(MediaTransform::error($invalid), json_encode($invalid) ?: '');
            $this->assertNull(MediaTransform::normalize($invalid));
        }
    }
}
