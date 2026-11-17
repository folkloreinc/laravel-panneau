<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\Boolean;
use Panneau\Fields\Date;
use Panneau\Fields\DateTime;
use Panneau\Fields\Money;
use Panneau\Fields\Number;
use Panneau\Fields\Relation;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Lists\Filters\DateRangeFilter;
use Panneau\Lists\Filters\RelationFilter;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use TestApp\Models\Run;

/**
 * A log of the runs of tools, read through its aggregates (panneau-js decision 0084): counted and
 * summed per day, per status, per author.
 */
class RunsResource extends Resource
{
    public const STATUSES = [
        ['value' => 'succeeded', 'label' => 'Succeeded', 'tone' => 'success'],
        ['value' => 'failed', 'label' => 'Failed', 'tone' => 'danger'],
        ['value' => 'skipped', 'label' => 'Skipped'],
    ];

    public function model(): string
    {
        return Run::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            Select::make('status')->options(self::STATUSES),
            Text::make('tool'),
            Boolean::make('retried'),
            Relation::make('author')->resource(AuthorsResource::class),
            Relation::make('watchers')->resource(AuthorsResource::class)->multiple(),
            Select::make('labels')->options(['urgent' => 'Urgent', 'night' => 'Night'])->multiple(),
            Number::make('cost'),
            Number::make('tokens'),
            // Only for admins: left out of the aggregates of the others
            Number::make('margin')->canSee(fn (): bool => false),
            Money::make('amount')->currency('CAD'),
            DateTime::make('ran_at'),
            Date::make('day'),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list
            ->columns(['title', 'status', 'ran_at'])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES)->multiple(),
                RelationFilter::make('author_id')->resource(AuthorsResource::class),
                DateRangeFilter::make('ran_at'),
            ])
            ->search(['title'])
            ->aggregates(
                sum: ['cost', 'tokens', 'amount', 'margin'],
                groupBy: ['status', 'tool', 'retried', 'author', 'watchers', 'labels'],
                dates: ['ran_at', 'day'],
            );
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1];
    }
}
