<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Actions\ActionPlacement;
use Panneau\Actions\ClientAction;
use Panneau\Actions\LinkAction;
use Panneau\Fields\Address;
use Panneau\Fields\BlockType;
use Panneau\Fields\Boolean;
use Panneau\Fields\Color;
use Panneau\Fields\Country;
use Panneau\Fields\DateRange;
use Panneau\Fields\DateTime;
use Panneau\Fields\Duration;
use Panneau\Fields\Embed;
use Panneau\Fields\Group;
use Panneau\Fields\Json;
use Panneau\Fields\Link;
use Panneau\Fields\Location;
use Panneau\Fields\Media;
use Panneau\Fields\Money;
use Panneau\Fields\Password;
use Panneau\Fields\Range;
use Panneau\Fields\Region;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Fields\RichText;
use Panneau\Fields\Select;
use Panneau\Fields\Slug;
use Panneau\Fields\Tags;
use Panneau\Fields\Tel;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Layout\Field;
use Panneau\Layout\Row;
use Panneau\Layout\Section;
use Panneau\Layout\Sidebar;
use Panneau\Layout\Tab;
use Panneau\Layout\Tabs;
use Panneau\Lists\Filters\DateRangeFilter;
use Panneau\Lists\Filters\RelationFilter;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\Filters\ToggleFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Duplication;
use Panneau\Resources\Resource;
use TestApp\Models\Post;
use TestApp\Models\PostStatus;
use TestApp\Panneau\Actions\ArchivePosts;
use TestApp\Panneau\Actions\FeaturePosts;
use TestApp\Panneau\Actions\PublishPosts;
use TestApp\Panneau\Actions\UnfeaturePosts;

class PostsResource extends Resource
{
    public function model(): string
    {
        return Post::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->label('Title')->required()->max(120),
            Slug::make('slug')->from('title')->localized()->unique()
                ->url(fn (string $slug, ?string $locale): string => 'https://example.com/'.$locale.'/posts/'.$slug),
            Text::make('subtitle')->localized(),
            RichText::make('body'),
            Select::make('status')->options(PostStatus::class)->required()->default('draft'),
            Boolean::make('featured'),
            DateTime::make('published_at'),
            // In two columns (AsDateRange), and in minor units with their currency (AsMoney)
            DateRange::make('dates')->allDay()->open('end')->chooseTimeZone(),
            Money::make('price')->currencies(['CAD', 'USD'])->min(0),
            Relation::make('author')->resource(AuthorsResource::class),
            Relation::make('tags')->resource(TagsResource::class)->multiple(),
            Group::make('seo')->fields([
                Text::make('title')->max(60),
                Textarea::make('description'),
            ]),
            Group::make('details')->fields([
                Tel::make('contact')->country('CA'),
                Range::make('rating')->max(10),
                Duration::make('reading_time')->precision('minutes'),
                Json::make('metadata'),
                Tags::make('keywords')->options(['culture' => 'Culture', 'food' => 'Food'])->max(5),
                Embed::make('video')->providers(['YouTube', 'Vimeo']),
                Location::make('place')->zoom(14)->center(45.5017, -73.5673),
                Country::make('country')->preferred(['CA', 'US']),
                Region::make('region')->from('country')->country('CA'),
                DateRange::make('period'),
                Money::make('budget')->currency('CAD'),
                Address::make('venue')->countries(['CA', 'US'])->requiredParts(['street', 'city', 'region', 'postalCode', 'country'])->point(),
                Link::make('website')->types(['url', ['id' => 'anchor', 'label' => 'Anchor', 'pattern' => '^#[\\w-]+$']])->newTab('external')->linkLabel('none'),
                Country::make('countries')->multiple()->max(5),
                Color::make('background')->palette([
                    ['value' => 'white', 'label' => 'White', 'color' => '#ffffff'],
                    ['value' => 'night', 'label' => 'Night', 'color' => '#1e1b4b'],
                ]),
            ]),
            // At the top of the resource (conformance: update.parts)
            Address::make('address')->country('CA')->preferred(['CA', 'US']),
            Link::make('link')->pages(AuthorsResource::class)
                ->resolvePageUrlUsing(fn (object $author, string $resource): string => 'https://example.com/'.$resource.'/'.$author->getKey()),
            Repeater::make('blocks')->types([
                BlockType::make('quote', 'Quote', [Textarea::make('text')->required(), Text::make('author')]),
                BlockType::make('image', 'Image', [Media::make('image')->accept('image/*')->required()]),
            ]),
            // Required to create a post only (conformance: update.create-rules)
            Password::make('preview_password'),
        ];
    }

    public function layout(): array
    {
        return [
            Tabs::make([
                Tab::make('Content', [
                    Row::make([Field::make('title')->width(8), Field::make('status')->width(4)]),
                    Field::make('subtitle'),
                    Field::make('body'),
                    Field::make('blocks'),
                ]),
                Tab::make('Sharing', [Section::make('SEO', [Field::make('seo')])->collapsible()]),
                Tab::make('Details', [Field::make('details')]),
            ]),
            Sidebar::make([Field::make('slug'), Field::make('published_at'), Field::make('author'), Field::make('tags')]),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list
            ->columns(['title', 'status', 'published_at'])
            ->filters([
                SelectFilter::make('status')->options(PostStatus::class)->multiple(),
                ToggleFilter::make('featured'),
                RelationFilter::make('author_id')->resource(AuthorsResource::class),
                DateRangeFilter::make('published_at'),
                // The ranges that overlap its days
                DateRangeFilter::make('dates'),
            ])
            ->sorts(['title', 'published_at', 'dates', 'price'])
            ->defaultSort('-published_at')
            ->search(['title', 'body']);
    }

    public function actions(): array
    {
        return [
            PublishPosts::class,
            ArchivePosts::class,
            FeaturePosts::class,
            UnfeaturePosts::class,
            LinkAction::make('preview')->url('/posts/{id}/preview')->placement([ActionPlacement::Form]),
            ClientAction::make('copy-link')->component('copy-link'),
        ];
    }

    public function duplication(): Duplication
    {
        return Duplication::make()->relations(['tags']);
    }

    public function duplicating(object $item, object $copy, Request $request): void
    {
        if ($item instanceof Post && $copy instanceof Post) {
            $copy->title = $item->title.' (copy)';
            $copy->status = 'draft';
        }
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1];
    }
}
