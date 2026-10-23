<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use TestApp\Models\Note;

/**
 * A resource on a model that uses Laravel Scout.
 */
class NotesResource extends Resource
{
    public function model(): string
    {
        return Note::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            Textarea::make('body'),
            Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list
            ->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])])
            ->sorts(['title'])
            ->defaultSort('title')
            ->search(['title']);
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1];
    }
}
