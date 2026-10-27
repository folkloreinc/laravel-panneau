<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Storage;
use Panneau\Fields\Image;
use Panneau\Fields\Media;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Models\Article;
use TestApp\Models\Asset;
use TestApp\Panneau\Resources\ImagesResource;

/**
 * `image` fields, the `crop` rule, the base of a transform, and the media's own transform
 * (panneau-js decision 0031).
 */
class ImageFieldTest extends HttpTestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Panneau::allowWithoutPolicies();
        Panneau::resources([ImagesResource::class, new class extends Resource
        {
            public function id(): string
            {
                return 'features';
            }

            public function model(): string
            {
                return Article::class;
            }

            public function fields(): array
            {
                return [
                    Text::make('title'),
                    Image::make('cover')
                        ->resource(ImagesResource::class)
                        ->ratios(['16:9', '4:3'])
                        ->rotate(false)
                        ->transformBase('choose')
                        ->crop(['16:9', '1:1'], required: true),
                    // The rule on a media field, without required
                    Media::make('gallery')->resource(ImagesResource::class)->multiple()->crop(['16:9']),
                ];
            }
        }]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function image(array $attributes = []): Asset
    {
        $path = 'images/'.uniqid();
        Storage::disk('public')->put($path, 'contents');

        return Asset::query()->create($attributes + [
            'team_id' => 1,
            'type' => 'image',
            'name' => 'Portrait',
            'file' => ['disk' => 'public', 'path' => $path, 'name' => 'portrait.jpg', 'mime' => 'image/jpeg', 'size' => 1000],
        ]);
    }

    /**
     * @param  array<string, mixed>  $transform
     * @return array<string, mixed>
     */
    private function cover(Asset $image, array $transform): array
    {
        return ['id' => (string) $image->id, 'transform' => $transform];
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}
     */
    private static function crop(int $width, int $height): array
    {
        return ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height];
    }

    public function test_the_definition_of_an_image_field(): void
    {
        $definition = $this->getJson(self::API.'/definition')->assertOk()->json();

        $this->assertValidDefinition($definition);
        $fields = collect(collect($definition['resources'])->firstWhere('id', 'features')['fields'])->keyBy('name');
        $this->assertSame([
            'name' => 'cover',
            'type' => 'image',
            'rules' => [
                ['rule' => 'accept', 'value' => ['image/*']],
                ['rule' => 'crop', 'value' => ['ratios' => ['16:9', '1:1'], 'required' => true]],
            ],
            'accept' => ['image/*'],
            'resource' => 'images',
            'ratios' => ['16:9', '4:3'],
            'rotate' => false,
            'transformBase' => 'choose',
        ], $fields['cover']);
        $this->assertSame([['rule' => 'crop', 'value' => ['ratios' => ['16:9'], 'required' => false]]], $fields['gallery']['rules']);
    }

    public function test_a_required_crop(): void
    {
        $image = $this->image();
        $errors = fn (mixed $cover): array => $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $cover])->assertUnprocessable()->json('errors');

        // An id alone has no crop
        $this->assertSame(['cover' => ['The cover needs a crop.']], $errors((string) $image->id));
        $this->assertSame(['cover' => ['The cover needs a crop.']], $errors(['id' => (string) $image->id]));
        $this->assertSame(['cover'], array_keys($errors($this->cover($image, ['rotate' => 90]))));

        $response = $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $this->cover($image, ['crop' => self::crop(1600, 900)])])
            ->assertCreated()
            ->assertJsonPath('data.cover.transform', ['crop' => self::crop(1600, 900)]);
        $this->assertSame(['id' => (string) $image->id, 'transform' => ['crop' => self::crop(1600, 900)]], Article::query()->findOrFail($response->json('data.id'))->cover);
        $this->assertMatchesComponent($response->json('data.cover'), 'MediaReference');
    }

    public function test_a_crop_has_one_of_the_ratios_once_rendered(): void
    {
        $image = $this->image();
        $status = fn (array $transform): int => $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $this->cover($image, $transform)])->status();

        $this->assertSame(201, $status(['crop' => self::crop(1600, 900)]));
        // Within one pixel
        $this->assertSame(201, $status(['crop' => self::crop(1601, 900)]));
        $this->assertSame(201, $status(['crop' => self::crop(800, 800)]));
        // A quarter turn swaps the width and the height
        $this->assertSame(201, $status(['crop' => self::crop(900, 1600), 'rotate' => 90]));
        $this->assertSame(201, $status(['crop' => self::crop(900, 1600), 'rotate' => 270]));
        $this->assertSame(422, $status(['crop' => self::crop(900, 1600), 'rotate' => 180]));
        $this->assertSame(422, $status(['crop' => self::crop(1200, 900)]));

        $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $this->cover($image, ['crop' => self::crop(1200, 900)])])
            ->assertJsonValidationErrors(['cover' => 'The crop of the cover must have one of these ratios: 16:9, 1:1.']);
    }

    public function test_the_crop_rule_on_a_media_field(): void
    {
        $image = $this->image();
        $cover = $this->cover($image, ['crop' => self::crop(1600, 900)]);

        // Without required, an image without a crop passes
        $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $cover, 'gallery' => [(string) $image->id, $this->cover($image, ['crop' => self::crop(1600, 900)])]])
            ->assertCreated();
        $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $cover, 'gallery' => [(string) $image->id, $this->cover($image, ['crop' => self::crop(800, 800)])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gallery.1' => 'The crop of the gallery must have one of these ratios: 16:9.'])
            ->assertJsonMissingValidationErrors(['gallery.0']);
    }

    public function test_a_transform_over_the_media_s_own(): void
    {
        $image = $this->image(['transform' => ['rotate' => 90]]);
        $transform = [
            'base' => 'media',
            'baseTransform' => ['rotate' => 90],
            'crop' => ['x' => 0, 'y' => 900, 'width' => 3024, 'height' => 1701],
        ];

        $response = $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $this->cover($image, $transform)])
            ->assertCreated()
            ->assertJsonPath('data.cover.transform', $transform);
        $article = Article::query()->findOrFail($response->json('data.id'));
        $this->assertSame(['id' => (string) $image->id, 'transform' => $transform], $article->cover);
        $this->getJson(self::API.'/resources/features/'.$article->id)->assertOk()->assertJsonPath('data.cover.transform', $transform);

        $error = fn (array $transform): array => $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $this->cover($image, $transform + ['crop' => self::crop(1600, 900)])])
            ->assertUnprocessable()
            ->json('errors.cover');
        $this->assertSame(['The transform of the cover is invalid: base must be media or original.'], $error(['base' => 'library']));
        $this->assertSame(['The transform of the cover is invalid: baseTransform: rotate must be 0, 90, 180 or 270.'], $error(['base' => 'media', 'baseTransform' => ['rotate' => 45]]));
        $this->assertSame(['The transform of the cover is invalid: baseTransform: must be an object.'], $error(['base' => 'media', 'baseTransform' => 'rotate']));
        // One level deep only
        $this->assertSame(
            ['The transform of the cover is invalid: baseTransform must be a transform without a base.'],
            $error(['base' => 'media', 'baseTransform' => ['base' => 'media', 'baseTransform' => ['rotate' => 90]]]),
        );
    }

    public function test_the_keys_of_editor_tools_are_kept(): void
    {
        $image = $this->image();
        $transform = ['crop' => self::crop(1600, 900), 'straighten' => 2.5, 'filters' => ['brightness' => 1.1]];

        $response = $this->postJson(self::API.'/resources/features', ['title' => 'A', 'cover' => $this->cover($image, $transform)])
            ->assertCreated()
            ->assertJsonPath('data.cover.transform', $transform);

        $this->getJson(self::API.'/resources/features/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.cover.transform', $transform);
    }

    public function test_references_give_the_media_s_own_transform(): void
    {
        $image = $this->image(['transform' => ['rotate' => 90, 'focalPoint' => ['x' => 0.5, 'y' => 0.25]]]);
        $article = Article::query()->create(['title' => 'A', 'cover' => ['id' => (string) $image->id, 'transform' => ['base' => 'media', 'baseTransform' => ['rotate' => 90], 'crop' => self::crop(1600, 900)]]]);

        // The library's item
        $item = $this->getJson(self::API.'/resources/images/'.$image->id)->assertOk();
        $item->assertJsonPath('data.media.transform', ['rotate' => 90, 'focalPoint' => ['x' => 0.5, 'y' => 0.25]]);
        $this->assertMatchesComponent($item->json('data.media'), 'MediaInfo');

        // The references of the fields that use it
        $reference = $this->getJson(self::API.'/resources/features/'.$article->id)->assertOk()->json('data.cover');
        $this->assertSame(['rotate' => 90, 'focalPoint' => ['x' => 0.5, 'y' => 0.25]], $reference['media']['transform']);
        $this->assertSame(['rotate' => 90], $reference['transform']['baseTransform']);
        $this->assertMatchesComponent($reference, 'MediaReference');

        // Once the media's transform changes, references give the new one
        $this->patchJson(self::API.'/resources/images/'.$image->id, ['transform' => ['rotate' => 180]])
            ->assertOk()
            ->assertJsonPath('data.media.transform', ['rotate' => 180]);
        $this->getJson(self::API.'/resources/features/'.$article->id)
            ->assertOk()
            ->assertJsonPath('data.cover.media.transform', ['rotate' => 180])
            ->assertJsonPath('data.cover.transform.baseTransform', ['rotate' => 90]);

        // A media without a transform has none
        $this->patchJson(self::API.'/resources/images/'.$image->id, ['transform' => null])->assertOk();
        $this->assertArrayNotHasKey('transform', $this->getJson(self::API.'/resources/features/'.$article->id)->assertOk()->json('data.cover.media'));
    }
}
