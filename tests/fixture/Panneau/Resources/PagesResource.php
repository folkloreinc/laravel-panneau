<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Panneau\Fields\Relation;
use Panneau\Fields\Slug;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\Page;

/**
 * Pages in a tree: a localized slug, unique among the pages of the same parent, whose URL
 * follows the parent's. The home page has no slug.
 */
class PagesResource extends Resource
{
    public function model(): string
    {
        return Page::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->localized()->required(),
            Text::make('handle')->readOnly(),
            Relation::make('parent')->resource(PagesResource::class),
            Slug::make('slug')->from('title')->localized()
                ->unique(fn (Builder $query, array $values) => $query->where(
                    'parent_id',
                    is_scalar($values['parent'] ?? null) ? $values['parent'] : null,
                ))
                ->url(function (string $slug, ?string $locale, array $values): string {
                    $parent = is_scalar($values['parent'] ?? null) ? Page::query()->find($values['parent']) : null;
                    $parentSlug = $parent?->slug[$locale] ?? null;

                    return 'https://example.com/'.$locale.'/'.($parentSlug !== null ? $parentSlug.'/' : '').$slug;
                })
                ->disabledWhen(fn (array $values): bool => ($values['handle'] ?? null) === 'home'),
        ];
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1];
    }
}
