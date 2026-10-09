<?php

namespace Panneau\Fields;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Panneau\Support\Field;
use Throwable;

class DateTime extends Field
{
    // TODO
    protected $format;

    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return 'date-time';
    }

    public function rules(Request $request): ?array
    {
        return [];
    }

    /**
     * The form sends the date in the browser timezone (2026-10-09T20:00:00-04:00).
     * Eloquent drops the offset when saving a datetime, so the value is converted to the
     * app timezone first (2026-10-10T00:00:00+00:00 for a UTC app).
     */
    public function prepareValue($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }
        try {
            $date = Carbon::parse($value);
        } catch (Throwable $e) {
            return $value;
        }
        return $date->setTimezone(config('app.timezone'))->toIso8601String();
    }
}
