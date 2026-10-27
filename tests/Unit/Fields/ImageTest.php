<?php

namespace Panneau\Tests\Unit\Fields;

use InvalidArgumentException;
use LogicException;
use Panneau\Fields\Image;
use Panneau\Fields\Media;
use Panneau\Tests\TestCase;

class ImageTest extends TestCase
{
    public function test_an_image_field_is_a_media_field_for_images(): void
    {
        $field = Image::make('cover');

        $this->assertInstanceOf(Media::class, $field);
        $this->assertSame([
            'name' => 'cover',
            'type' => 'image',
            'rules' => [['rule' => 'accept', 'value' => ['image/*']]],
            'accept' => ['image/*'],
        ], $field->toDefinition());
        $this->assertTrue($field->hasAccept());
        $this->assertSame(['image/png'], Image::make('cover')->accept('image/png')->toDefinition()['accept']);
        $this->assertSame([['rule' => 'accept', 'value' => ['image/png']]], Image::make('cover')->accept('image/png')->toDefinition()['rules']);
    }

    public function test_the_options_of_the_editor(): void
    {
        $this->assertSame([
            'name' => 'cover',
            'type' => 'image',
            'rules' => [['rule' => 'accept', 'value' => ['image/*']]],
            'multiple' => true,
            'accept' => ['image/*'],
            'resource' => ['images', 'photos'],
            'ratios' => ['16:9', '1:1'],
            'rotate' => false,
            'focalPoint' => true,
            'transformBase' => 'original',
        ], Image::make('cover')
            ->resource('images', 'photos')
            ->multiple()
            ->ratios(['16:9', '1:1', '16:9'])
            ->rotate(false)
            ->focalPoint()
            ->transformBase('original')
            ->toDefinition());
    }

    public function test_the_crop_rule(): void
    {
        $this->assertSame(
            [['rule' => 'crop', 'value' => ['ratios' => ['16:9', '1:1'], 'required' => true], 'message' => 'Crop it.']],
            Media::make('cover')->crop(['16:9', '1:1'], required: true, message: 'Crop it.')->toDefinition()['rules'],
        );
        $this->assertSame([['rule' => 'crop', 'value' => ['required' => true]]], Media::make('cover')->crop(required: true)->toDefinition()['rules']);
    }

    public function test_an_invalid_ratio_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Image::make('cover')->ratios(['16/9']);
    }

    public function test_an_invalid_ratio_of_the_crop_rule_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Image::make('cover')->crop(['0:1']);
    }

    public function test_an_invalid_transform_base_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Image::make('cover')->transformBase('library');
    }

    public function test_a_field_that_keeps_ids_has_no_crop_rule(): void
    {
        $this->expectException(LogicException::class);
        Media::make('cover')->transforms(false)->crop(required: true);
    }
}
