<?php

namespace Panneau\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use Closure;
use InvalidArgumentException;
use Panneau\Fields\Boolean;
use Panneau\Fields\Color;
use Panneau\Fields\Date;
use Panneau\Fields\DateTime;
use Panneau\Fields\Duration;
use Panneau\Fields\Embed;
use Panneau\Fields\Group;
use Panneau\Fields\Json;
use Panneau\Fields\Location;
use Panneau\Fields\Number;
use Panneau\Fields\Range;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Fields\RichText;
use Panneau\Fields\Select;
use Panneau\Fields\Slug;
use Panneau\Fields\Tags;
use Panneau\Fields\Tel;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Fields\Time;
use Panneau\Forms\Condition;
use Panneau\Lists\Filters\SelectFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TestApp\Models\PostStatus;

class FieldTest extends TestCase
{
    public function test_types(): void
    {
        $this->assertSame(
            [
                'text', 'textarea', 'richtext', 'number', 'boolean', 'date', 'datetime', 'time', 'select', 'color', 'slug', 'relation', 'group', 'repeater',
                'tel', 'range', 'duration', 'json', 'tags', 'embed', 'location',
            ],
            array_map(fn ($field) => $field->type(), [
                Text::make('a'), Textarea::make('a'), RichText::make('a'), Number::make('a'), Boolean::make('a'),
                Date::make('a'), DateTime::make('a'), Time::make('a'), Select::make('a'), Color::make('a'),
                Slug::make('a'), Relation::make('a'), Group::make('a'), Repeater::make('a'),
                Tel::make('a'), Range::make('a'), Duration::make('a'), Json::make('a'), Tags::make('a'), Embed::make('a'), Location::make('a'),
            ]),
        );
    }

    public function test_common_modifiers(): void
    {
        $field = Text::make('title')
            ->label('Title')
            ->help('Shown in search results')
            ->placeholder('A title')
            ->default(null)
            ->localized()
            ->readOnly()
            ->createOnly()
            ->updateOnly()
            ->visibleWhen(Condition::field('type')->equals('page'))
            ->display('bold')
            ->settings(['rows' => 2]);

        $this->assertSame([
            'name' => 'title',
            'type' => 'text',
            'label' => 'Title',
            'help' => 'Shown in search results',
            'placeholder' => 'A title',
            'display' => 'bold',
            'default' => null,
            'localized' => true,
            'readOnly' => true,
            'createOnly' => true,
            'updateOnly' => true,
            'visibleWhen' => ['field' => 'type', 'equals' => 'page'],
            'settings' => ['rows' => 2],
        ], $field->toDefinition());
    }

    public function test_rules_replace_the_same_rule(): void
    {
        $field = Text::make('title')->max(10)->max(20, 'Too long')->pattern('^a', 'i')->before(field: 'end');

        $this->assertSame([
            ['rule' => 'max', 'value' => 20, 'message' => 'Too long'],
            ['rule' => 'pattern', 'value' => '^a', 'flags' => 'i'],
            ['rule' => 'before', 'value' => ['field' => 'end']],
        ], $field->toDefinition()['rules']);
    }

    public function test_type_properties(): void
    {
        $this->assertSame(['name' => 'slug', 'type' => 'slug', 'from' => 'title'], Slug::make('slug')->from('title')->toDefinition());
        $this->assertSame(['step' => 1], Number::make('count')->integer()->toDefinition()['settings']);
        $this->assertSame(
            [['value' => 1, 'label' => 'One'], ['value' => 'b', 'label' => 'B', 'disabled' => true]],
            Select::make('choice')->options([1 => 'One', ['value' => 'b', 'label' => 'B', 'disabled' => true]])->toDefinition()['options'],
        );
        $this->assertSame([['value' => 'draft', 'label' => 'Draft']], array_slice(Select::make('status')->options(PostStatus::class)->toDefinition()['options'], 0, 1));
    }

    public function test_a_secret_text(): void
    {
        $this->assertSame(
            ['name' => 'key', 'type' => 'text', 'settings' => ['secret' => true]],
            Text::make('key')->secret()->toDefinition(),
        );
        $this->assertSame('x', Text::make('key')->secret()->resolve((object) ['key' => 'x']));
    }

    public function test_a_write_only_text(): void
    {
        $field = Text::make('key')->secret()->writeOnly();

        $this->assertNull($field->resolve((object) ['key' => 'x']));
        $this->assertTrue($field->keepsStoredValue(null));
        $this->assertTrue($field->keepsStoredValue(''));
        $this->assertFalse($field->keepsStoredValue('y'));
        $this->assertFalse(Text::make('key')->keepsStoredValue(null));
        $this->assertSame('x', $field->writeOnly(false)->resolve((object) ['key' => 'x']));
    }

    public function test_options_have_tones(): void
    {
        $tones = [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published', 'tone' => 'success'],
            ['value' => 'archived', 'label' => 'Archived', 'tone' => 'warning'],
        ];

        $this->assertSame($tones, Select::make('status')->options(PostStatus::class)->toDefinition()['options']);
        $this->assertSame($tones, Tags::make('status')->options(PostStatus::class)->toDefinition()['options']);
        $this->assertSame($tones, SelectFilter::make('status')->options(PostStatus::class)->toDefinition()['options']);
        $this->assertSame(
            [['value' => 'failed', 'label' => 'Failed', 'tone' => 'danger'], ['value' => 'late', 'label' => 'Late', 'tone' => 'brand-2']],
            Select::make('status')->options([
                ['value' => 'failed', 'label' => 'Failed', 'tone' => 'danger'],
                ['value' => 'late', 'label' => 'Late', 'tone' => 'brand-2'],
            ])->toDefinition()['options'],
        );
    }

    public function test_settings_of_the_new_types(): void
    {
        $this->assertSame(
            ['name' => 'opacity', 'type' => 'range', 'rules' => [['rule' => 'min', 'value' => 10]], 'settings' => ['step' => 5, 'decimals' => 0, 'suffix' => '%']],
            Range::make('opacity')->min(10)->step(5)->decimals(0)->suffix('%')->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'length', 'type' => 'duration', 'settings' => ['precision' => 'minutes']],
            Duration::make('length')->precision('minutes')->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'sections', 'type' => 'tags', 'options' => [['value' => 'food', 'label' => 'Food']], 'settings' => ['allowCustom' => false]],
            Tags::make('sections')->options(['food' => 'Food'])->allowCustom(false)->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'place', 'type' => 'location', 'settings' => [
                'zoom' => 14,
                'center' => ['lat' => 45.5, 'lng' => -73.56],
                'tiles' => ['url' => 'https://tiles.example.com/{z}/{x}/{y}.png', 'attribution' => '© Example'],
            ]],
            Location::make('place')->zoom(14)->center(45.5, -73.56)->tiles('https://tiles.example.com/{z}/{x}/{y}.png', '© Example')->toDefinition(),
        );
    }

    public function test_rich_text_settings(): void
    {
        $this->assertSame(
            ['name' => 'body', 'type' => 'richtext', 'settings' => [
                'toolbar' => ['heading', 'style', '|', 'bold', 'italic', 'link', 'logo'],
                'headings' => [2, 4],
                'styles' => [
                    ['label' => 'Big paragraph', 'class' => 'big'],
                    ['label' => 'Small heading', 'class' => 'small', 'element' => 'h2'],
                ],
                'links' => ['newTab' => 'external'],
            ]],
            RichText::make('body')
                ->toolbar(['heading', 'style', '|', 'bold', 'italic', 'link', 'logo'])
                ->headings(2, 4)
                ->style('big', 'Big paragraph')
                ->style('small', 'Small heading', 'h2')
                ->linksInNewTab('external')
                ->toDefinition(),
        );
        $this->assertSame(['name' => 'body', 'type' => 'richtext'], RichText::make('body')->toDefinition());
    }

    /**
     * @return array<string, array{Closure(): mixed}>
     */
    public static function invalidRichTextSettings(): array
    {
        return [
            'heading level 7' => [fn () => RichText::make('body')->headings(2, 7)],
            'heading level 0' => [fn () => RichText::make('body')->headings(0)],
            'new tab always' => [fn () => RichText::make('body')->linksInNewTab('always')],
            'style on a div' => [fn () => RichText::make('body')->style('big', 'Big', 'div')],
            'style without class' => [fn () => RichText::make('body')->style(' ', 'Big')],
            'empty toolbar item' => [fn () => RichText::make('body')->toolbar(['bold', ''])],
        ];
    }

    #[DataProvider('invalidRichTextSettings')]
    public function test_rich_text_settings_the_protocol_refuses_throw(Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    public function test_values_are_read_from_any_object(): void
    {
        $item = (object) [
            'title' => 'Title',
            'published_at' => CarbonImmutable::parse('2026-10-15 10:30:00', 'UTC'),
            'status' => PostStatus::Published,
            'featured' => 1,
            'seo' => ['title' => 'SEO', 'secret' => 'x'],
            'links' => [(object) ['url' => 'https://a.test', 'secret' => 'x']],
        ];

        $this->assertSame('Title', Text::make('title')->resolve($item));
        $this->assertSame('2026-10-15T10:30:00+00:00', DateTime::make('published_at')->resolve($item));
        $this->assertSame('2026-10-15', Date::make('published_at')->resolve($item));
        $this->assertSame('10:30:00', Time::make('published_at')->resolve($item));
        $this->assertSame('published', Select::make('status')->resolve($item));
        $this->assertTrue(Boolean::make('featured')->resolve($item));
        $this->assertSame(['title' => 'SEO'], Group::make('seo')->fields([Text::make('title')])->resolve($item));
        $this->assertSame([['url' => 'https://a.test']], Repeater::make('links')->fields([Text::make('url')])->resolve($item));
        $this->assertNull(Text::make('missing')->resolve($item));
        $this->assertSame('TITLE', Text::make('title')->resolveUsing(fn (object $item) => strtoupper($item->title))->resolve($item));
    }
}
