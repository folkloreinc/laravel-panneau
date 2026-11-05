<?php

namespace Panneau\Tests\Feature\Definition;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Panneau\Dashboard\Widget;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Navigation\Page;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Dashboard\OverdueWidget;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\NotesFeedResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\TagsResource;

/**
 * The widgets of the dashboard, in the definition (panneau-js decision 0064).
 */
class DashboardTest extends TestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([PostsResource::class, AuthorsResource::class, TagsResource::class, NotesFeedResource::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(?Authenticatable $user = null): array
    {
        return $this->app->make(DefinitionBuilder::class)->build($user);
    }

    public function test_no_dashboard_by_default(): void
    {
        $definition = $this->definition();

        $this->assertArrayNotHasKey('dashboard', $definition);
        $this->assertArrayNotHasKey('pages', $definition);
    }

    public function test_the_widgets_and_the_dashboard_as_the_home_page(): void
    {
        Panneau::dashboard([
            Widget::stat('drafts')->label('Drafts')->resource(PostsResource::class, ['filter[status]' => 'draft']),
            Widget::list('latest')->label('Latest posts')->width(4)->resource('posts', ['sort' => '-published_at']),
            Widget::stat('overdue')->label('Overdue')->using(OverdueWidget::class),
            Widget::series('per-day')->using(fn (): array => ['period' => 'day', 'points' => []])->width(8),
            Widget::make('map', 'map')->settings(['city' => 'Montréal'])->using(fn (): array => []),
        ]);

        $definition = $this->definition();
        $this->assertSame(['home' => ['component' => 'dashboard']], $definition['pages']);
        $this->assertSame([
            ['id' => 'drafts', 'type' => 'stat', 'label' => 'Drafts', 'settings' => ['resource' => 'posts', 'query' => ['filter[status]' => 'draft']]],
            ['id' => 'latest', 'type' => 'list', 'label' => 'Latest posts', 'width' => 4, 'settings' => ['resource' => 'posts', 'query' => ['sort' => '-published_at']]],
            ['id' => 'overdue', 'type' => 'stat', 'label' => 'Overdue'],
            ['id' => 'per-day', 'type' => 'series', 'width' => 8],
            ['id' => 'map', 'type' => 'map', 'settings' => ['city' => 'Montréal']],
        ], $definition['dashboard']['widgets']);
        $this->assertValidDefinition($definition);
    }

    public function test_a_home_page_of_the_project_stays(): void
    {
        Panneau::pages([Page::make('home', 'welcome')]);
        Panneau::dashboard([Widget::stat('overdue')->using(OverdueWidget::class)]);

        $this->assertSame(['home' => ['component' => 'welcome']], $this->definition()['pages']);
    }

    public function test_the_widgets_of_each_user(): void
    {
        Gate::define('reports.view', fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 1);
        Panneau::dashboard(fn (?Authenticatable $user): array => [
            Widget::stat('overdue')->using(OverdueWidget::class)->can('reports.view'),
            ...($user?->getAuthIdentifier() === 2 ? [Widget::list('latest')->resource('posts')] : []),
        ]);

        $ids = fn (?Authenticatable $user): array => array_column($this->definition($user)['dashboard']['widgets'] ?? [], 'id');
        $this->assertSame(['overdue'], $ids(new GenericUser(['id' => 1, 'name' => 'Ada'])));
        $this->assertSame(['latest'], $ids(new GenericUser(['id' => 2, 'name' => 'Grace'])));
        // Without widgets, the cards of the resources
        $this->assertArrayNotHasKey('dashboard', $this->definition());
        $this->assertArrayNotHasKey('pages', $this->definition());
    }

    /**
     * @return array<string, array{list<Widget>, string}>
     */
    public static function invalidDashboards(): array
    {
        return [
            'a widget without data' => [[Widget::stat('a')], 'The widget "a" has no data: give it a resource() or using().'],
            'a resource that is not registered' => [[Widget::list('a')->resource('nope')], 'The widget "a" reads the resource nope, which is not registered.'],
            'a filter of no list' => [[Widget::stat('a')->resource('posts', ['filter[color]' => 'red'])], 'The widget "a" filters the list of "posts" by "color", which is not one of its filters.'],
            'a stat of a list paged with cursors' => [[Widget::stat('a')->resource(NotesFeedResource::class)], 'The stat "a" counts the list of "notes-feed", which is paged with cursors and has no total.'],
            'two widgets of the same id' => [[Widget::stat('a')->resource('posts'), Widget::list('a')->resource('posts')], 'The dashboard has two widgets "a": ids are unique.'],
        ];
    }

    /**
     * @param  list<Widget>  $widgets
     */
    #[DataProvider('invalidDashboards')]
    public function test_invalid_dashboards_throw_when_the_definition_is_built(array $widgets, string $message): void
    {
        Panneau::dashboard($widgets);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->definition();
    }

    public function test_a_list_paged_with_cursors_can_feed_a_list(): void
    {
        Panneau::dashboard([Widget::list('notes')->resource(NotesFeedResource::class)]);

        $this->assertSame('notes-feed', $this->definition()['dashboard']['widgets'][0]['settings']['resource']);
    }
}
