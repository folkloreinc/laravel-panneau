<?php

namespace Panneau\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use Panneau\Fields\Boolean;
use Panneau\Fields\Color;
use Panneau\Fields\Date;
use Panneau\Fields\DateTime;
use Panneau\Fields\Group;
use Panneau\Fields\Number;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Fields\RichText;
use Panneau\Fields\Select;
use Panneau\Fields\Slug;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Fields\Time;
use Panneau\Forms\Condition;
use PHPUnit\Framework\TestCase;
use TestApp\Models\PostStatus;

class FieldTest extends TestCase
{
    public function test_types(): void
    {
        $this->assertSame(
            ['text', 'textarea', 'richtext', 'number', 'boolean', 'date', 'datetime', 'time', 'select', 'color', 'slug', 'relation', 'group', 'repeater'],
            array_map(fn ($field) => $field->type(), [
                Text::make('a'), Textarea::make('a'), RichText::make('a'), Number::make('a'), Boolean::make('a'),
                Date::make('a'), DateTime::make('a'), Time::make('a'), Select::make('a'), Color::make('a'),
                Slug::make('a'), Relation::make('a'), Group::make('a'), Repeater::make('a'),
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
