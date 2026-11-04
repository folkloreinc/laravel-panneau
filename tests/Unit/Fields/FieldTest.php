<?php

namespace Panneau\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use Closure;
use InvalidArgumentException;
use Panneau\Fields\Boolean;
use Panneau\Fields\Color;
use Panneau\Fields\Country;
use Panneau\Fields\Date;
use Panneau\Fields\DateRange;
use Panneau\Fields\DateTime;
use Panneau\Fields\Duration;
use Panneau\Fields\Embed;
use Panneau\Fields\Group;
use Panneau\Fields\Json;
use Panneau\Fields\Location;
use Panneau\Fields\Money;
use Panneau\Fields\Number;
use Panneau\Fields\Range;
use Panneau\Fields\Region;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Fields\RichText;
use Panneau\Fields\Select;
use Panneau\Fields\Sessions;
use Panneau\Fields\Slug;
use Panneau\Fields\Tags;
use Panneau\Fields\Tel;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Fields\Time;
use Panneau\Forms\Condition;
use Panneau\Forms\ValidationContext;
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
                'tel', 'range', 'duration', 'json', 'tags', 'embed', 'location', 'country', 'region',
                'daterange', 'money', 'repeater',
            ],
            array_map(fn ($field) => $field->type(), [
                Text::make('a'), Textarea::make('a'), RichText::make('a'), Number::make('a'), Boolean::make('a'),
                Date::make('a'), DateTime::make('a'), Time::make('a'), Select::make('a'), Color::make('a'),
                Slug::make('a'), Relation::make('a'), Group::make('a'), Repeater::make('a'),
                Tel::make('a'), Range::make('a'), Duration::make('a'), Json::make('a'), Tags::make('a'), Embed::make('a'), Location::make('a'),
                Country::make('a'), Region::make('a'),
                DateRange::make('a'), Money::make('a'), Sessions::make('a'),
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
        $context = new ValidationContext;
        $this->assertTrue($field->omits(null, $context));
        $this->assertTrue($field->omits('', $context));
        $this->assertFalse($field->omits('y', $context));
        $this->assertFalse(Text::make('key')->omits(null, $context));
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

    public function test_countries_regions_and_phone_numbers(): void
    {
        $this->assertSame(
            ['name' => 'country', 'type' => 'country', 'settings' => ['countries' => ['CA', 'US', 'FR'], 'preferred' => ['CA', 'US']]],
            Country::make('country')->countries(['CA', 'US', 'FR'])->preferred(['CA', 'US'])->toDefinition(),
        );
        $this->assertSame(['name' => 'country', 'type' => 'country'], Country::make('country')->toDefinition());
        $this->assertSame(
            ['name' => 'countries', 'type' => 'country', 'rules' => [['rule' => 'max', 'value' => 3]], 'multiple' => true],
            Country::make('countries')->multiple()->max(3)->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'province', 'type' => 'region', 'from' => 'country', 'settings' => ['country' => 'CA']],
            Region::make('province')->from('country')->country('CA')->toDefinition(),
        );
        $this->assertSame(['name' => 'province', 'type' => 'region'], Region::make('province')->toDefinition());
        // The tel rule is implied by the type, as json is: it isn't in the definition
        $this->assertSame(
            ['name' => 'phone', 'type' => 'tel', 'settings' => ['country' => 'CA']],
            Tel::make('phone')->country('CA')->toDefinition(),
        );
    }

    public function test_the_country_codes(): void
    {
        $this->assertCount(250, Country::CODES);
        $this->assertSame(Country::CODES, array_values(array_unique(Country::CODES)));
        $sorted = Country::CODES;
        sort($sorted);
        $this->assertSame($sorted, Country::CODES);
        $this->assertContains('XK', Country::CODES);
        $this->assertSame(['CA', 'US'], Country::make('country')->countries(['CA', 'US'])->allowed());
        $this->assertSame(Country::CODES, Country::make('country')->allowed());
    }

    /**
     * @return array<string, array{Closure(): mixed}>
     */
    public static function invalidCountryCodes(): array
    {
        return [
            'lowercase countries' => [fn () => Country::make('country')->countries(['ca'])],
            'unknown preferred' => [fn () => Country::make('country')->preferred(['CA', 'XX'])],
            'region with a region code' => [fn () => Region::make('region')->country('QC')],
            'tel with an alpha-3 code' => [fn () => Tel::make('phone')->country('CAN')],
        ];
    }

    /**
     * @param  Closure(): mixed  $build
     */
    #[DataProvider('invalidCountryCodes')]
    public function test_setters_refuse_codes_that_are_not_iso(Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    public function test_duration_units(): void
    {
        $this->assertSame(['name' => 'length', 'type' => 'duration'], Duration::make('length')->toDefinition());
        $this->assertSame(
            ['name' => 'running_time', 'type' => 'duration', 'settings' => ['unit' => 'minutes']],
            Duration::make('running_time')->unit('minutes')->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'window', 'type' => 'duration', 'settings' => ['unit' => 'hours', 'largestUnit' => 'days', 'precision' => 'hours']],
            Duration::make('window')->unit('hours')->largestUnit('days')->precision('hours')->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'half_life', 'type' => 'duration', 'settings' => ['precision' => 'days']],
            Duration::make('half_life')->precision('days')->toDefinition(),
        );
        $this->assertSame(['seconds', 'minutes', 'hours', 'days'], Duration::UNITS);
    }

    /**
     * @return array<string, array{Closure(): mixed}>
     */
    public static function invalidDurationUnits(): array
    {
        return [
            'unit in weeks' => [fn () => Duration::make('length')->unit('weeks')],
            'unit in months' => [fn () => Duration::make('length')->unit('months')],
            'largest unit in years' => [fn () => Duration::make('length')->largestUnit('years')],
            'precision in milliseconds' => [fn () => Duration::make('length')->precision('milliseconds')],
            'singular unit' => [fn () => Duration::make('length')->unit('minute')],
            'capitalized unit' => [fn () => Duration::make('length')->unit('Hours')],
        ];
    }

    /**
     * @param  Closure(): mixed  $build
     */
    #[DataProvider('invalidDurationUnits')]
    public function test_duration_setters_refuse_other_units(Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
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

    public function test_embeds_name_their_providers_in_a_portable_rule(): void
    {
        $this->assertSame(
            ['name' => 'trailer', 'type' => 'embed', 'rules' => [['rule' => 'providers', 'value' => ['YouTube', 'Vimeo']]]],
            Embed::make('trailer')->providers(['YouTube', 'Vimeo'])->toDefinition(),
        );
    }

    public function test_embeds_need_providers_by_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Embed::make('trailer')->providers([]);
    }

    public function test_colors_take_a_palette(): void
    {
        $this->assertSame(
            ['name' => 'background', 'type' => 'color', 'settings' => ['palette' => [
                ['value' => 'purple', 'label' => 'Purple', 'color' => '#6b21a8'],
                ['value' => '#ffffff'],
            ]]],
            Color::make('background')->palette([
                ['value' => 'purple', 'label' => 'Purple', 'color' => '#6b21a8'],
                ['value' => '#ffffff'],
            ])->toDefinition(),
        );
        $this->assertSame(['name' => 'accent', 'type' => 'color'], Color::make('accent')->toDefinition());
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function invalidPalettes(): array
    {
        return [
            'empty' => [[]],
            'without a value' => [[['label' => 'Purple', 'color' => '#6b21a8']]],
            'a name without a color' => [[['value' => 'purple']]],
            'a color that is not hexadecimal' => [[['value' => 'purple', 'color' => 'purple']]],
            'a label that is not a string' => [[['value' => '#fff', 'label' => 12]]],
            'an unknown key' => [[['value' => '#fff', 'tone' => 'accent']]],
            'a repeated value' => [[['value' => '#fff'], ['value' => '#fff']]],
            'not a list of colors' => [['#fff']],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $palette
     */
    #[DataProvider('invalidPalettes')]
    public function test_palettes_are_checked(array $palette): void
    {
        $this->expectException(InvalidArgumentException::class);
        Color::make('background')->palette($palette);
    }

    public function test_sortable_fields(): void
    {
        foreach ([Text::make('a'), Number::make('a'), Range::make('a'), Money::make('a'), Date::make('a'), DateTime::make('a'), Time::make('a'), DateRange::make('a'), Duration::make('a'), Boolean::make('a'), Select::make('a'), Country::make('a'), Slug::make('a'), Tel::make('a')] as $field) {
            $this->assertTrue($field->isSortable(), $field->type());
        }
        foreach ([Textarea::make('a'), RichText::make('a'), Color::make('a'), Json::make('a'), Relation::make('a'), Tags::make('a'), Group::make('a'), Repeater::make('a'), Location::make('a'), Embed::make('a')] as $field) {
            $this->assertFalse($field->isSortable(), $field->type());
        }
        // Several values, one value per locale, or a value the item doesn't keep as it is
        $this->assertFalse(Select::make('a')->multiple()->isSortable());
        $this->assertFalse(Country::make('a')->multiple()->isSortable());
        $this->assertFalse(Text::make('a')->localized()->isSortable());
        $this->assertFalse(Text::make('a')->resolveUsing(fn (object $item): string => 'computed')->isSortable());
    }
}
