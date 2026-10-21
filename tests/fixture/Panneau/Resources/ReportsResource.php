<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Fields\File;
use Panneau\Fields\Repeater;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\Report;

class ReportsResource extends Resource
{
    public function model(): string
    {
        return Report::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            File::make('terms')
                ->disk('files')
                ->directory('terms')
                ->accept(['application/pdf'])
                ->maxSize(10_000, 'The terms are too large.'),
            File::make('cover')->disk('public')->directory('covers')->visibility('public')->accept('image/*'),
            File::make('attachments')->disk('files')->multiple()->max(3),
            Repeater::make('sections')->fields([
                Text::make('heading'),
                File::make('file')->disk('files')->directory('sections'),
            ]),
        ];
    }

    /**
     * A title of "Refused" fails the save after the files were stored.
     */
    public function creating(array $values, Request $request): array
    {
        return self::refuse($values);
    }

    public function updating(object $item, array $values, Request $request): array
    {
        return self::refuse($values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function refuse(array $values): array
    {
        if (($values['title'] ?? null) === 'Refused') {
            throw ValidationException::withMessages(['title' => 'This title is refused.']);
        }

        return $values;
    }
}
