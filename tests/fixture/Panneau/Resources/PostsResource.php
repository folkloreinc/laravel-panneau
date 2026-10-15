<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Actions\ActionPlacement;
use Panneau\Actions\ClientAction;
use Panneau\Actions\LinkAction;
use Panneau\Fields\BlockType;
use Panneau\Fields\Boolean;
use Panneau\Fields\DateTime;
use Panneau\Fields\Group;
use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Fields\RichText;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Layout\Field;
use Panneau\Layout\Row;
use Panneau\Layout\Section;
use Panneau\Layout\Sidebar;
use Panneau\Layout\Tab;
use Panneau\Layout\Tabs;
use Panneau\Lists\Filters\RelationFilter;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\Filters\ToggleFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use TestApp\Models\Post;
use TestApp\Models\PostStatus;
use TestApp\Panneau\Actions\PublishPosts;

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
            Text::make('subtitle')->localized(),
            RichText::make('body'),
            Select::make('status')->options(PostStatus::class)->required()->default('draft'),
            Boolean::make('featured'),
            DateTime::make('published_at'),
            Relation::make('author')->resource(AuthorsResource::class),
            Relation::make('tags')->resource(TagsResource::class)->multiple(),
            Group::make('seo')->fields([
                Text::make('title')->max(60),
                Textarea::make('description'),
            ]),
            Repeater::make('blocks')->types([
                BlockType::make('quote', 'Quote', [Textarea::make('text')->required(), Text::make('author')]),
                BlockType::make('image', 'Image', [Media::make('image')->accept('image/*')->required()]),
            ]),
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
            ]),
            Sidebar::make([Field::make('published_at'), Field::make('author'), Field::make('tags')]),
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
            ])
            ->sorts(['title', 'published_at'])
            ->defaultSort('-published_at')
            ->search(['title', 'body']);
    }

    public function actions(): array
    {
        return [
            PublishPosts::class,
            LinkAction::make('preview')->url('/posts/{id}/preview')->placement([ActionPlacement::Form]),
            ClientAction::make('copy-link')->component('copy-link'),
        ];
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1];
    }
}
