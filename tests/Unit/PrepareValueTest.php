<?php

namespace Panneau\Tests\Unit;

use Panneau\Fields\Date;
use Panneau\Fields\DateTime;
use Panneau\Fields\Fields;
use Panneau\Fields\Items;
use Panneau\Fields\Text;
use Panneau\Support\Field;
use Panneau\Tests\TestCase;

class PrepareValueTest extends TestCase
{
    public function test_date_time_is_converted_to_app_timezone()
    {
        config(['app.timezone' => 'UTC']);
        $field = DateTime::make('startsAt');

        $this->assertEquals(
            '2026-10-10T00:00:00+00:00',
            $field->prepareValue('2026-10-09T20:00:00-04:00')
        );
        $this->assertEquals(
            '2026-10-10T00:00:00+00:00',
            $field->prepareValue('2026-10-10T00:00:00.000000Z')
        );
    }

    public function test_date_time_is_converted_to_non_utc_app_timezone()
    {
        config(['app.timezone' => 'America/Toronto']);
        $field = DateTime::make('startsAt');

        $this->assertEquals(
            '2026-10-09T20:00:00-04:00',
            $field->prepareValue('2026-10-10T00:00:00.000000Z')
        );
    }

    public function test_date_time_keeps_empty_or_invalid_values()
    {
        $field = DateTime::make('startsAt');

        $this->assertNull($field->prepareValue(null));
        $this->assertEquals('', $field->prepareValue(''));
        $this->assertEquals('not a date', $field->prepareValue('not a date'));
    }

    public function test_date_is_not_converted()
    {
        config(['app.timezone' => 'UTC']);

        $this->assertEquals('2026-10-01', Date::make('publishedAt')->prepareValue('2026-10-01'));
    }

    public function test_nested_fields_are_prepared()
    {
        config(['app.timezone' => 'UTC']);

        $fields = [
            Text::make('title'),
            DateTime::make('startsAt'),
            Fields::make('event')->withFields([DateTime::make('endsAt')]),
            new class('dates') extends Items
            {
                public function fields(): ?array
                {
                    return [DateTime::make('startsAt')];
                }
            },
        ];

        $data = Field::prepareFieldsRequestData($fields, [
            'title' => 'Show',
            'startsAt' => '2026-10-09T20:00:00-04:00',
            'event' => ['endsAt' => '2026-10-09T22:00:00-04:00'],
            'dates' => [['startsAt' => '2026-10-10T20:00:00-04:00']],
        ]);

        $this->assertEquals(
            [
                'title' => 'Show',
                'startsAt' => '2026-10-10T00:00:00+00:00',
                'event' => ['endsAt' => '2026-10-10T02:00:00+00:00'],
                'dates' => [['startsAt' => '2026-10-11T00:00:00+00:00']],
            ],
            $data
        );
    }

    public function test_missing_values_are_not_added()
    {
        $data = Field::prepareFieldsRequestData([DateTime::make('startsAt')], ['title' => 'Show']);

        $this->assertEquals(['title' => 'Show'], $data);
    }
}
