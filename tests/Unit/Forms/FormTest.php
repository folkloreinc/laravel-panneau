<?php

namespace Panneau\Tests\Unit\Forms;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Panneau\Fields\BlockType;
use Panneau\Fields\Boolean;
use Panneau\Fields\Date;
use Panneau\Fields\Email;
use Panneau\Fields\Field;
use Panneau\Fields\Group;
use Panneau\Fields\Media;
use Panneau\Fields\Number;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Fields\Select;
use Panneau\Fields\Slug;
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
            ['blocks' => [['type' => 'quote', 'text' => 'Hi'], ['type' => 'image', 'image' => 'med_1']]],
            $this->validate($fields, ['blocks' => [['type' => 'quote', 'text' => 'Hi', 'image' => 'x'], ['type' => 'image', 'image' => 'med_1']]]),
        );
    }

    public function test_media(): void
    {
        $fields = [
            Media::make('image')->accept('image/*')->maxSize(1000),
            Media::make('files')->multiple()->accept(['application/pdf']),
        ];

        $this->assertSame(
            ['image', 'files'],
            array_keys($this->errors($fields, ['image' => ['id' => '1', 'mime' => 'image/png', 'size' => 2000], 'files' => [['id' => '2', 'mime' => 'image/png']]])),
        );
        $this->assertSame(['image'], array_keys($this->errors($fields, ['image' => ['name' => 'no id']])));
        $this->assertSame(
            ['image' => 'med_1', 'files' => [['id' => '2', 'mime' => 'application/pdf']]],
            $this->validate($fields, ['image' => 'med_1', 'files' => [['id' => '2', 'mime' => 'application/pdf']]]),
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
}
