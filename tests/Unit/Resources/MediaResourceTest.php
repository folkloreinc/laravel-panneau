<?php

namespace Panneau\Tests\Unit\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Panneau\Fields\Field;
use Panneau\Fields\File;
use Panneau\Fields\Text;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\MediaResource;
use Panneau\Tests\TestCase;

class MediaResourceTest extends TestCase
{
    /**
     * @param  list<string>  $types
     * @param  list<Field>  $fields
     * @param  (\Closure(File): File)|null  $file
     * @param  (\Closure(ResourceList): ResourceList)|null  $list
     */
    private static function library(array $types = [], array $fields = [], ?\Closure $file = null, ?\Closure $list = null): MediaResource
    {
        return new class($types, $fields, $file, $list) extends MediaResource
        {
            /**
             * @param  list<string>  $types
             * @param  list<Field>  $libraryFields
             * @param  (\Closure(File): File)|null  $configureFile
             * @param  (\Closure(ResourceList): ResourceList)|null  $configureList
             */
            public function __construct(
                private array $types,
                private array $libraryFields,
                private ?\Closure $configureFile,
                private ?\Closure $configureList,
            ) {}

            public function id(): string
            {
                return 'media';
            }

            public function mediaTypes(): array
            {
                return $this->types;
            }

            protected function file(File $file): File
            {
                return $this->configureFile !== null ? ($this->configureFile)($file) : $file;
            }

            public function fields(): array
            {
                return $this->libraryFields;
            }

            public function list(ResourceList $list): ResourceList
            {
                return $this->configureList !== null ? ($this->configureList)($list) : $list;
            }

            public function scope(Request $request): array
            {
                return ['team_id' => 1];
            }
        };
    }

    public function test_a_library_has_a_file_field_first(): void
    {
        $library = self::library(['image', 'video'], [Text::make('name')]);

        $this->assertSame('media', $library->kind());
        $this->assertSame(['file', 'name'], array_map(fn ($field): string => $field->name(), $library->allFields()));
        $this->assertSame(['name'], array_map(fn ($field): string => $field->name(), $library->fields()));
        $this->assertSame([
            'name' => 'file',
            'type' => 'file',
            'rules' => [['rule' => 'required'], ['rule' => 'accept', 'value' => ['image/*', 'video/*']]],
            'accept' => ['image/*', 'video/*'],
        ], $library->allFields()[0]->toDefinition());
        $this->assertSame($library->fileField(), $library->field('file'));
        $this->assertSame('name', $library->itemTitle());
    }

    public function test_the_file_is_configured_by_the_library(): void
    {
        $library = self::library(['image'], file: fn (File $file): File => $file->accept('image/png')->maxSize(100)->disk('s3'));
        $this->assertSame(['image/png'], $library->fileField()->toDefinition()['accept']);
        $this->assertSame('s3', $library->fileField()->getDisk());

        // Types whose MIME types can't be listed leave the file open
        $this->assertArrayNotHasKey('accept', self::library(['image', 'other'])->fileField()->toDefinition());
        $this->assertArrayNotHasKey('accept', self::library()->fileField()->toDefinition());
    }

    public function test_the_file_is_one_file_named_file(): void
    {
        $this->expectException(LogicException::class);
        self::library(file: fn (File $file): File => $file->multiple())->allFields();
    }

    public function test_fields_dont_declare_the_file_or_the_media(): void
    {
        foreach (['file', 'media'] as $name) {
            try {
                self::library(fields: [Text::make($name)])->allFields();
                $this->fail('A library accepted a field named '.$name);
            } catch (LogicException $exception) {
                $this->assertStringContainsString('"'.$name.'"', $exception->getMessage());
            }
        }
    }

    public function test_the_scope_has_the_media_types(): void
    {
        $request = Request::create('/');

        $this->assertSame(['team_id' => 1, 'type' => ['image', 'video']], self::library(['image', 'video', 'image'])->repositoryScope($request));
        $this->assertSame(['team_id' => 1], self::library()->repositoryScope($request));
        $this->assertSame(['team_id' => 1], self::library(['image'])->scope($request));
    }

    public function test_the_list_has_a_type_filter(): void
    {
        $library = self::library(['image', 'document'], list: fn (ResourceList $list): ResourceList => $list->filters([SelectFilter::make('credit')->options(['a' => 'A'])]));
        $filters = $library->makeList()->getFilters();

        $this->assertSame(['type', 'credit'], array_map(fn ($filter): string => $filter->id(), $filters));
        $this->assertSame(['image', 'document'], array_column($filters[0]->toDefinition()['options'], 'value'));
        $this->assertSame(['image'], $filters[0]->normalize('image'));
        $this->assertSame(['video', 'font'], $filters[0]->normalize(['video', 'font', 'Not a type', 3]));
        $this->assertNull($filters[0]->normalize('../etc'));

        // A list that declares its own type filter keeps it
        $own = SelectFilter::make('type')->options(['image' => 'Pictures']);
        $this->assertSame([$own], self::library(list: fn (ResourceList $list): ResourceList => $list->filters([$own]))->makeList()->getFilters());
    }

    public function test_the_media_of_an_item_comes_from_its_file(): void
    {
        Storage::fake('public');
        $library = self::library(file: fn (File $file): File => $file->disk('public')->visibility('public'));
        $url = Storage::disk('public')->url('a/photo.png');

        $this->assertSame(
            ['type' => 'image', 'mime' => 'image/png', 'size' => 10, 'url' => $url, 'thumbnailUrl' => $url],
            $library->media((object) ['file' => ['disk' => 'public', 'path' => 'a/photo.png', 'name' => 'photo.png', 'mime' => 'image/png', 'size' => 10]]),
        );
        $this->assertSame(
            ['type' => 'video', 'mime' => 'image/png', 'size' => 10, 'url' => $url, 'thumbnailUrl' => null],
            $library->media((object) ['type' => 'video', 'file' => ['disk' => 'public', 'path' => 'a/photo.png', 'name' => 'photo.png', 'mime' => 'image/png', 'size' => 10]]),
        );
        $this->assertSame(
            ['type' => 'other', 'mime' => null, 'size' => null, 'url' => null, 'thumbnailUrl' => null],
            $library->media((object) ['file' => null]),
        );
    }

    public function test_the_label_of_a_media(): void
    {
        Storage::fake('public');
        $this->app->setLocale('en');
        $file = ['disk' => 'public', 'path' => 'a/photo.png', 'name' => 'photo.png', 'mime' => 'image/png', 'size' => 10];

        $this->assertSame('Portrait', self::library(fields: [Text::make('name')])->mediaLabel((object) ['name' => 'Portrait', 'file' => $file]));
        $this->assertSame('Portrait', self::library(fields: [Text::make('name')->localized()])->mediaLabel((object) ['name' => ['fr' => 'Portrait FR', 'en' => 'Portrait'], 'file' => $file]));
        $this->assertSame('Portrait FR', self::library(fields: [Text::make('name')->localized()])->mediaLabel((object) ['name' => ['fr' => 'Portrait FR'], 'file' => $file]));
        $this->assertSame('photo.png', self::library(fields: [Text::make('name')])->mediaLabel((object) ['name' => null, 'file' => $file]));
        $this->assertNull(self::library()->mediaLabel((object) ['file' => null]));
    }
}
