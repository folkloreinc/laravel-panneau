<?php

namespace Panneau\Tests\Unit\Forms;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Panneau\Contracts\EmbedResolver;
use Panneau\Fields\BlockType;
use Panneau\Fields\Boolean;
use Panneau\Fields\Date;
use Panneau\Fields\Duration;
use Panneau\Fields\Email;
use Panneau\Fields\Embed;
use Panneau\Fields\Field;
use Panneau\Fields\Group;
use Panneau\Fields\Json;
use Panneau\Fields\Location;
use Panneau\Fields\Media;
use Panneau\Fields\Number;
use Panneau\Fields\Range;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Fields\Select;
use Panneau\Fields\Slug;
use Panneau\Fields\Tags;
use Panneau\Fields\Tel;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Fields\Time;
use Panneau\Fields\Url;
use Panneau\Forms\Condition;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;
use Panneau\Tests\TestCase;
use TestApp\Models\PostStatus;

class FormTest extends TestCase
{
    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $fields, array $input, ?ValidationContext $context = null): array
    {
        return Form::validate($this->app['validator'], $fields, $input, $context ?? new ValidationContext(locales: ['fr', 'en']));
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    private function errors(array $fields, array $input, ?ValidationContext $context = null): array
    {
        try {
            $this->validate($fields, $input, $context);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }

    public function test_only_declared_values_are_kept(): void
    {
        $values = $this->validate(
            [Text::make('title'), Text::make('body')],
            ['title' => 'Title', 'is_admin' => true, 'id' => 4],
        );

        $this->assertSame(['title' => 'Title'], $values);
    }

    public function test_required_on_create(): void
    {
        $errors = $this->errors([Text::make('title')->required()], []);

        $this->assertArrayHasKey('title', $errors);
    }

    public function test_partial_updates_only_validate_present_values(): void
    {
        $fields = [Text::make('title')->required(), Text::make('body')->required()];
        $context = new ValidationContext(FormMode::Update, ['en'], partial: true);

        $this->assertSame(['body' => 'Body'], $this->validate($fields, ['body' => 'Body'], $context));
        $this->assertArrayHasKey('title', $this->errors($fields, ['title' => ''], $context));
    }

    public function test_optional_values_can_be_null(): void
    {
        $this->assertSame(['title' => null], $this->validate([Text::make('title')], ['title' => null]));
    }

    public function test_types(): void
    {
        $fields = [
            Text::make('text'),
            Number::make('number'),
            Number::make('integer')->integer(),
            Boolean::make('boolean'),
            Date::make('date'),
            Time::make('time'),
            Email::make('email'),
            Url::make('url'),
        ];

        $this->assertSame(
            ['text', 'number', 'integer', 'boolean', 'date', 'time', 'email', 'url'],
            array_keys($this->errors($fields, [
                'text' => ['array'],
                'number' => 'abc',
                'integer' => 1.5,
                'boolean' => 'maybe',
                'date' => '2026-13-45',
                'time' => '25:00',
                'email' => 'not an email',
                'url' => 'not a url',
            ])),
        );
        $this->assertSame(
            ['text' => 'a', 'number' => 1.5, 'integer' => 2, 'boolean' => false, 'date' => '2026-10-15', 'time' => '10:30', 'email' => 'a@example.com', 'url' => 'https://example.com'],
            $this->validate($fields, ['text' => 'a', 'number' => 1.5, 'integer' => 2, 'boolean' => '0', 'date' => '2026-10-15', 'time' => '10:30', 'email' => 'a@example.com', 'url' => 'https://example.com']),
        );
    }

    public function test_portable_rules(): void
    {
        $fields = [
            Text::make('code')->min(2)->max(4)->pattern('^[a-z/]+$'),
            Number::make('count')->min(1)->max(10),
            Text::make('choice')->in(['a', 'b']),
            Date::make('start'),
            Date::make('end')->after(field: 'start'),
            Date::make('deadline')->before('2030-01-01'),
        ];

        $this->assertSame(
            ['code', 'count', 'choice', 'end', 'deadline'],
            array_keys($this->errors($fields, [
                'code' => 'ABCDE',
                'count' => 11,
                'choice' => 'c',
                'start' => '2026-10-15',
                'end' => '2026-10-01',
                'deadline' => '2031-01-01',
            ])),
        );
        $this->assertSame(
            ['code' => 'a/b', 'count' => 5, 'choice' => 'a', 'start' => '2026-10-15', 'end' => '2026-10-20', 'deadline' => '2029-01-01'],
            $this->validate($fields, ['code' => 'a/b', 'count' => 5, 'choice' => 'a', 'start' => '2026-10-15', 'end' => '2026-10-20', 'deadline' => '2029-01-01']),
        );
    }

    public function test_pattern_flags(): void
    {
        $fields = [Text::make('code')->pattern('^[a-z]+$', 'i')];

        $this->assertSame(['code' => 'ABC'], $this->validate($fields, ['code' => 'ABC']));
    }

    public function test_rule_messages(): void
    {
        $errors = $this->errors([Text::make('title')->required('Give it a title.')->max(3, 'Too long.')], ['title' => '']);
        $this->assertSame(['title' => ['Give it a title.']], $errors);

        $errors = $this->errors([Text::make('title')->max(3, 'Too long.')], ['title' => 'abcd']);
        $this->assertSame(['title' => ['Too long.']], $errors);
    }

    public function test_server_rules(): void
    {
        $fields = [Text::make('slug')->rules([Rule::notIn(['admin'])])];
        $this->assertArrayHasKey('slug', $this->errors($fields, ['slug' => 'admin']));

        $item = (object) ['id' => 3];
        $fields = [Text::make('slug')->rules(fn (ValidationContext $context): array => [
            fn (string $attribute, mixed $value, \Closure $fail) => $context->item === $item ? null : $fail('No item.'),
        ])];
        $this->assertSame([], $this->errors($fields, ['slug' => 'a'], new ValidationContext(FormMode::Update, item: $item)));
        $this->assertArrayHasKey('slug', $this->errors($fields, ['slug' => 'a']));
    }

    public function test_select_only_accepts_its_options(): void
    {
        $fields = [
            Select::make('status')->options(PostStatus::class),
            Select::make('colors')->options(['red' => 'Red', 'blue' => 'Blue'])->multiple(),
        ];

        $this->assertSame(['status', 'colors.1'], array_keys($this->errors($fields, ['status' => 'deleted', 'colors' => ['red', 'green']])));
        $this->assertSame(['status' => 'draft', 'colors' => ['red']], $this->validate($fields, ['status' => 'draft', 'colors' => ['red']]));
    }

    public function test_localized_values(): void
    {
        $fields = [Text::make('title')->localized()->required()->max(5)];

        $this->assertSame(
            ['title.fr', 'title.en'],
            array_keys($this->errors($fields, ['title' => ['fr' => '', 'en' => 'Too long']])),
        );
        $this->assertSame(
            ['title' => ['fr' => 'Titre', 'en' => 'Title']],
            $this->validate($fields, ['title' => ['fr' => 'Titre', 'en' => 'Title', 'de' => 'Titel']]),
        );
    }

    public function test_read_only_create_only_and_update_only(): void
    {
        $fields = [
            Text::make('code')->readOnly(),
            Slug::make('slug')->createOnly(),
            Text::make('reason')->updateOnly(),
        ];
        $input = ['code' => 'A', 'slug' => 'a', 'reason' => 'Fix'];

        $this->assertSame(['slug' => 'a'], $this->validate($fields, $input));
        $this->assertSame(['reason' => 'Fix'], $this->validate($fields, $input, new ValidationContext(FormMode::Update)));
    }

    public function test_hidden_fields_are_neither_validated_nor_kept(): void
    {
        $fields = [
            Select::make('type')->options(['link' => 'Link', 'text' => 'Text']),
            Url::make('url')->required()->visibleWhen(Condition::field('type')->equals('link')),
            Textarea::make('text')->required()->visibleWhen(['field' => 'type', 'in' => ['text']]),
        ];

        $this->assertSame(['type' => 'text', 'text' => 'Hello'], $this->validate($fields, ['type' => 'text', 'url' => 'not a url', 'text' => 'Hello']));
        $this->assertSame(['url'], array_keys($this->errors($fields, ['type' => 'link'])));
    }

    public function test_groups(): void
    {
        $fields = [Group::make('seo')->fields([Text::make('title')->required(), Text::make('description')])];

        $this->assertSame(['seo.title'], array_keys($this->errors($fields, ['seo' => ['description' => 'D']])));
        $this->assertSame(
            ['seo' => ['title' => 'T']],
            $this->validate($fields, ['seo' => ['title' => 'T', 'secret' => 'x']]),
        );
        $this->assertSame(['seo'], array_keys($this->errors($fields, ['seo' => 'text'])));
    }

    public function test_repeaters(): void
    {
        $fields = [Repeater::make('links')->fields([Url::make('url')->required(), Text::make('label')])->max(2)];

        $this->assertSame(['links.1.url'], array_keys($this->errors($fields, ['links' => [['url' => 'https://a.test'], ['label' => 'B']]])));
        $this->assertSame(['links'], array_keys($this->errors($fields, ['links' => [['url' => 'https://a.test'], ['url' => 'https://b.test'], ['url' => 'https://c.test']]])));
        $this->assertSame(
            ['links' => [['url' => 'https://a.test']]],
            $this->validate($fields, ['links' => [['url' => 'https://a.test', 'extra' => true]]]),
        );
    }

    public function test_typed_repeaters(): void
    {
        $fields = [Repeater::make('blocks')->types([
            BlockType::make('quote', 'Quote', [Textarea::make('text')->required()]),
            BlockType::make('image', 'Image', [Media::make('image')->required()]),
        ])];

        $this->assertSame(
            ['blocks.0.text', 'blocks.1.image', 'blocks.2.type'],
            array_keys($this->errors($fields, ['blocks' => [['type' => 'quote'], ['type' => 'image'], ['type' => 'video']]])),
        );
        $this->assertSame(
            ['blocks' => [['type' => 'quote', 'text' => 'Hi'], ['type' => 'image', 'image' => ['id' => 'med_1']]]],
            $this->validate($fields, ['blocks' => [['type' => 'quote', 'text' => 'Hi', 'image' => 'x'], ['type' => 'image', 'image' => 'med_1']]]),
        );
    }

    public function test_media(): void
    {
        $fields = [
            Media::make('image')->accept('image/*')->maxSize(1000),
            Media::make('files')->multiple()->accept(['application/pdf']),
        ];
        $media = [
            'png' => ['type' => 'image', 'mime' => 'image/png', 'size' => 500],
            'large' => ['type' => 'image', 'mime' => 'image/png', 'size' => 2000],
            'pdf' => ['type' => 'document', 'mime' => 'application/pdf', 'size' => 100],
        ];
        $context = new ValidationContext(media: fn (?string $library, string $id): ?array => $media[$id] ?? null);

        // accept and maxSize are checked on the media found, never on what the client sends
        $this->assertSame(
            ['image', 'files.0'],
            array_keys($this->errors($fields, ['image' => ['id' => 'large', 'mime' => 'image/png', 'size' => 10], 'files' => [['id' => 'png', 'mime' => 'application/pdf']]], $context)),
        );
        $this->assertSame(['image', 'files.1'], array_keys($this->errors($fields, ['image' => 'missing', 'files' => ['pdf', ['name' => 'no id']]], $context)));
        $this->assertSame(['image'], array_keys($this->errors($fields, ['image' => ['id' => 'png', 'transform' => ['rotate' => 45]]], $context)));
        $this->assertSame(['files'], array_keys($this->errors($fields, ['files' => 'pdf'], $context)));
        $this->assertSame(
            [
                'image' => ['id' => 'png', 'transform' => ['crop' => ['x' => 0, 'y' => 10, 'width' => 100, 'height' => 50], 'rotate' => 90, 'focalPoint' => ['x' => 0.5, 'y' => 0.25], 'extra' => true]],
                'files' => [['id' => 'pdf']],
            ],
            $this->validate($fields, [
                'image' => [
                    'id' => 'png',
                    'label' => 'A reference sent back',
                    'media' => ['type' => 'image'],
                    'transform' => ['crop' => ['x' => 0, 'y' => 10, 'width' => 100, 'height' => 50], 'rotate' => 90, 'flipX' => false, 'focalPoint' => ['x' => 0.5, 'y' => 0.25], 'extra' => true],
                ],
                'files' => ['pdf'],
            ], $context),
        );

        // Without a library to look in, only the shape of the values is checked
        $this->assertSame(['image' => ['id' => 'med_1'], 'files' => [['id' => '2']]], $this->validate($fields, ['image' => 'med_1', 'files' => [['id' => 2]]]));
        $this->assertSame(['image'], array_keys($this->errors($fields, ['image' => ['name' => 'no id']])));
    }

    public function test_media_of_several_libraries(): void
    {
        $fields = [
            Media::make('cover')->resource('images', 'documents'),
            Media::make('gallery')->multiple(),
            Media::make('logo')->resource('images'),
        ];
        $media = ['images' => ['1' => ['type' => 'image', 'mime' => 'image/png']], 'documents' => ['1' => ['type' => 'document', 'mime' => 'application/pdf']]];
        $context = new ValidationContext(
            media: fn (?string $library, string $id): ?array => $media[$library][$id] ?? null,
            // Every library when the field names none
            libraries: fn (array $libraries): array => count($libraries) > 0 ? $libraries : ['images', 'documents'],
        );

        $this->assertSame(
            [
                'cover' => ['id' => '1', 'resource' => 'documents', 'transform' => ['rotate' => 90]],
                'gallery' => [['id' => '1', 'resource' => 'images'], ['id' => '1', 'resource' => 'documents']],
                'logo' => ['id' => '1'],
            ],
            $this->validate($fields, [
                'cover' => ['id' => '1', 'resource' => 'documents', 'label' => 'Sent back', 'transform' => ['rotate' => 90]],
                'gallery' => [['id' => '1', 'resource' => 'images'], ['id' => 1, 'resource' => 'documents']],
                'logo' => ['id' => '1', 'resource' => 'images'],
            ], $context),
        );
        $this->assertSame(
            ['cover', 'logo', 'gallery.0', 'gallery.1'],
            array_keys($this->errors($fields, [
                'cover' => '1',
                'gallery' => [['id' => '1'], ['id' => '1', 'resource' => 'videos']],
                'logo' => ['id' => '1', 'resource' => 'documents'],
            ], $context)),
        );
    }

    public function test_media_without_transforms_keep_ids(): void
    {
        $fields = [Media::make('cover')->transforms(false), Media::make('gallery')->transforms(false)->multiple()];

        $this->assertSame(
            ['cover' => 'med_1', 'gallery' => ['med_2', 'med_3']],
            $this->validate($fields, ['cover' => ['id' => 'med_1', 'transform' => ['rotate' => 45]], 'gallery' => ['med_2', ['id' => 'med_3']]]),
        );
    }

    public function test_localized_media_are_checked_per_locale(): void
    {
        $fields = [Media::make('poster')->localized()->accept('image/*')];
        $context = new ValidationContext(locales: ['fr', 'en'], media: fn (?string $library, string $id): ?array => $id === 'pdf'
            ? ['type' => 'document', 'mime' => 'application/pdf']
            : ['type' => 'image', 'mime' => 'image/png']);

        $this->assertSame(['poster.en'], array_keys($this->errors($fields, ['poster' => ['fr' => 'png', 'en' => 'pdf']], $context)));
        $this->assertSame(
            ['poster' => ['fr' => ['id' => 'png'], 'en' => null]],
            $this->validate($fields, ['poster' => ['fr' => 'png', 'en' => null]], $context),
        );
    }

    public function test_relations_keep_ids(): void
    {
        $fields = [
            Relation::make('author')->resource('authors'),
            Relation::make('tags')->resource('tags')->multiple(),
        ];
        $exists = fn (string $resource, string $id): bool => $id !== 'missing';
        $context = new ValidationContext(relationExists: $exists);

        $this->assertSame(
            ['author' => '12', 'tags' => ['a', 'b']],
            $this->validate($fields, ['author' => ['id' => '12', 'label' => 'Ada'], 'tags' => ['a', ['id' => 'b']]], $context),
        );
        $this->assertSame(
            ['author', 'tags.1'],
            array_keys($this->errors($fields, ['author' => 'missing', 'tags' => ['a', 'missing']], $context)),
        );
        $this->assertSame(['author'], array_keys($this->errors($fields, ['author' => ['label' => 'No id']], $context)));
    }

    public function test_prepare_hands_each_validated_value_to_its_field(): void
    {
        $make = fn (string $name): Text => new class($name) extends Text
        {
            /**
             * @var list<string>
             */
            public array $calls = [];

            protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
            {
                $this->calls[] = 'prepare '.$path;

                return is_string($value) ? strtoupper($value) : $value;
            }

            public function prepareAbsent(string $path, SaveContext $context): void
            {
                $this->calls[] = 'absent '.$path;
            }
        };
        $fields = [
            $code = $make('code'),
            Group::make('meta')->fields([$inGroup = $make('code')]),
            Repeater::make('rows')->fields([$inRows = $make('code')]),
        ];

        $this->assertSame(
            ['code' => 'X', 'meta' => [], 'rows' => [['code' => 'Y'], []]],
            Form::prepare($fields, ['code' => 'x', 'meta' => [], 'rows' => [['code' => 'y'], []]], new SaveContext),
        );
        $this->assertSame(['prepare code'], $code->calls);
        // A group and blocks are saved whole: their fields without a value are told.
        $this->assertSame(['absent meta.code'], $inGroup->calls);
        $this->assertSame(['prepare rows.0.code', 'absent rows.1.code', 'absent rows.*.code'], $inRows->calls);

        // A partial update leaves out the fields it doesn't send.
        $code->calls = [];
        $this->assertSame([], Form::prepare([$code], [], new SaveContext(FormMode::Update)));
        $this->assertSame([], $code->calls);
    }

    public function test_tel_range_and_duration(): void
    {
        $fields = [
            Tel::make('phone')->pattern('^[0-9 +-]+$'),
            Range::make('rating'),
            Range::make('score')->min(-5)->max(5),
            Duration::make('length'),
        ];

        $this->assertSame(
            ['phone', 'rating', 'score', 'length'],
            array_keys($this->errors($fields, ['phone' => 'call me', 'rating' => 101, 'score' => -6, 'length' => 90.5])),
        );
        $this->assertSame(['rating', 'length'], array_keys($this->errors($fields, ['rating' => -1, 'length' => -60])));
        $this->assertSame(
            ['phone' => '+1 514 555-0123', 'rating' => 0, 'score' => -5, 'length' => 3930],
            $this->validate($fields, ['phone' => '+1 514 555-0123', 'rating' => 0, 'score' => -5, 'length' => 3930]),
        );
    }

    public function test_json_values_are_objects_or_arrays(): void
    {
        $fields = [Json::make('metadata')];

        // The text of JSON that doesn't parse, as the field sends it
        $this->assertArrayHasKey('metadata', $this->errors($fields, ['metadata' => '{ "a": 1, }']));
        $this->assertArrayHasKey('metadata', $this->errors($fields, ['metadata' => 12]));
        $this->assertSame(['metadata' => ['a' => ['b' => 1]]], $this->validate($fields, ['metadata' => ['a' => ['b' => 1]]]));
        $this->assertSame(['metadata' => [1, 2]], $this->validate($fields, ['metadata' => [1, 2]]));
        $this->assertSame(['metadata' => null], $this->validate($fields, ['metadata' => null]));
    }

    public function test_tags(): void
    {
        $fields = [
            Tags::make('tags')->max(3),
            Tags::make('sections')->options(['food' => 'Food', 'music' => 'Music'])->allowCustom(false),
        ];

        $this->assertSame(
            ['tags', 'tags.1', 'sections.1'],
            array_keys($this->errors($fields, ['tags' => ['a', ['b'], 'c', 'd'], 'sections' => ['food', 'travel']])),
        );
        $values = $this->validate($fields, ['tags' => [' summer ', 'montreal', 'summer'], 'sections' => ['music']]);
        $this->assertSame(['tags' => [' summer ', 'montreal', 'summer'], 'sections' => ['music']], $values);
        // Saving trims the tags and drops empty and repeated ones
        $this->assertSame(
            ['tags' => ['summer', 'montreal'], 'sections' => ['music']],
            Form::prepare($fields, $values, new SaveContext),
        );
    }

    public function test_locations(): void
    {
        $fields = [Location::make('place')];

        $this->assertSame(
            ['place.lat', 'place.lng'],
            array_keys($this->errors($fields, ['place' => ['lat' => 91, 'lng' => 'east']])),
        );
        $this->assertArrayHasKey('place.lng', $this->errors($fields, ['place' => ['lat' => 45.5]]));
        $this->assertSame(
            ['place' => ['lat' => 45.509595, 'lng' => -73.565912, 'address' => 'Place des Arts, Montréal']],
            $this->validate($fields, ['place' => ['lat' => 45.509595, 'lng' => -73.565912, 'address' => ' Place des Arts, Montréal ', 'zoom' => 3]]),
        );
        $this->assertSame(['place' => ['lat' => 0.0, 'lng' => 0.0]], $this->validate($fields, ['place' => ['lat' => 0, 'lng' => 0, 'address' => '']]));
        $this->assertSame(['place' => null], $this->validate($fields, ['place' => null]));
    }

    public function test_embeds_keep_the_url_and_are_resolved_when_saved(): void
    {
        $resolved = [];
        $this->app->instance(EmbedResolver::class, new class($resolved) implements EmbedResolver
        {
            /**
             * @param  list<string>  $resolved
             */
            public function __construct(private array &$resolved) {}

            public function resolve(string $url): ?array
            {
                $this->resolved[] = $url;

                return str_contains($url, 'youtube')
                    ? ['url' => $url, 'type' => 'video', 'title' => 'A video', 'html' => '<iframe></iframe>']
                    : null;
            }
        });
        $fields = [Embed::make('video')];

        $this->assertArrayHasKey('video.url', $this->errors($fields, ['video' => ['url' => 'javascript:alert(1)']]));
        $this->assertArrayHasKey('video.url', $this->errors($fields, ['video' => ['title' => 'No URL']]));

        // Only the URL comes from the request: the rest is the provider's
        $values = $this->validate($fields, ['video' => ['url' => 'https://www.youtube.com/watch?v=1', 'html' => '<script></script>']]);
        $this->assertSame(['video' => ['url' => 'https://www.youtube.com/watch?v=1']], $values);
        $this->assertSame(
            ['video' => ['url' => 'https://www.youtube.com/watch?v=1', 'type' => 'video', 'title' => 'A video', 'html' => '<iframe></iframe>']],
            Form::prepare($fields, $values, new SaveContext),
        );

        // A URL that can't be resolved is saved alone
        $this->assertSame(
            ['video' => ['url' => 'https://example.com/post']],
            Form::prepare($fields, ['video' => ['url' => 'https://example.com/post']], new SaveContext),
        );

        // An unchanged URL keeps the item's value
        $item = (object) ['video' => ['url' => 'https://example.com/post', 'title' => 'Kept']];
        $this->assertSame(
            ['video' => ['url' => 'https://example.com/post', 'title' => 'Kept']],
            Form::prepare($fields, ['video' => ['url' => 'https://example.com/post']], new SaveContext(FormMode::Update, $item)),
        );
        $this->assertSame(['https://www.youtube.com/watch?v=1', 'https://example.com/post'], $resolved);
        $this->assertSame(['video' => null], Form::prepare($fields, ['video' => null], new SaveContext));
    }
}
