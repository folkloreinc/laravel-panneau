<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Panneau\Dashboard\Row;
use Panneau\Dashboard\Source;
use Panneau\Dashboard\Widget;
use Panneau\Dashboard\WidgetRequest;
use Panneau\Exceptions\WidgetDataException;
use Panneau\Http\Problem;
use Panneau\Navigation\Page;
use Panneau\Support\Facade as Panneau;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Dashboard\OverdueWidget;
use TestApp\Dashboard\RequestWidget;

/**
 * `GET /dashboard/widgets/{widget}` (panneau-js decisions 0064 and 0084).
 */
class DashboardEndpointTest extends HttpTestCase
{
    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        OverdueWidget::$calls = 0;
        Panneau::dashboard(fn (?Authenticatable $user): array => [
            Widget::stat('drafts')->resource('posts', ['filter[status]' => 'draft']),
            Widget::stat('overdue')->label('Overdue')->using(OverdueWidget::class),
            Widget::series('per-day')->using(fn (): array => [
                'period' => 'day',
                'points' => [['date' => '2026-10-05', 'value' => 4], ['date' => '2026-10-06', 'value' => 8]],
                'total' => 12,
            ]),
            Widget::list('contests')->using(fn (?Authenticatable $user): array => [
                'items' => [['title' => 'Spring contest', 'meta' => '12 entries', 'badge' => ['label' => 'Open', 'tone' => 'success']]],
                'empty' => 'No open contests.',
            ]),
            Widget::make('map', 'map')->using(fn (): array => []),
            Widget::breakdown('states')->using(fn (): array => [
                'items' => [['label' => 'Open', 'value' => 3, 'tone' => 'success', 'url' => '/contests?state=open'], ['label' => 'Closed', 'value' => 1]],
                'total' => 5,
                'other' => 1,
            ]),
            Widget::breakdown('wrong-parts')->using(fn (): array => ['items' => [['value' => 'x']]]),
            Widget::stat('home-period')->using(fn (WidgetRequest $request): array => ['value' => $request->period === null ? 0 : 1]),
            Widget::stat('budget')->using(fn (): array => ['value' => 120, 'target' => 100, 'tone' => 'danger']),
            Widget::stat('cached')->cache(60)->using(function (?Authenticatable $user): array {
                $this->calls++;

                return ['value' => $this->calls, 'help' => app()->getLocale()];
            }),
            Widget::stat('secret')->using(fn (): array => throw new AuthorizationException),
            Widget::stat('broken')->cache(60)->using(function (): array {
                $this->calls++;

                return ['value' => 'many', 'tone' => 'Red', 'secret' => 'never sent'];
            }),
            ...($user?->getAuthIdentifier() === 1 ? [Widget::stat('ada')->using(fn (): array => ['value' => 1])] : []),
            // Combined widgets (panneau-js decision 0089)
            Widget::table('costs')->currency('USD')->using(fn (): array => [
                'columns' => [['key' => 'cost', 'label' => 'Cost'], ['key' => 'calls', 'label' => 'Calls', 'format' => ['style' => 'decimal']]],
                'rows' => [
                    ['label' => 'Summaries', 'url' => '/runs', 'cells' => ['cost' => 9.08, 'calls' => ['value' => 6, 'tone' => 'danger', 'help' => 'failed']], 'points' => [['date' => '2026-10-05', 'value' => 3]]],
                    ['label' => 'Translations', 'cells' => []],
                ],
                'period' => 'day',
                'format' => [],
            ]),
            Widget::series('messages')->stacked()->using(fn (): array => [
                'period' => 'day',
                'points' => [['date' => '2026-10-05', 'value' => 7]],
                'series' => [
                    ['key' => 'emails', 'label' => 'Emails', 'points' => [['date' => '2026-10-05', 'value' => 3]], 'total' => 3, 'alert' => ['value' => 1, 'label' => 'failed']],
                    ['key' => 'texts', 'label' => 'Text messages', 'points' => [['date' => '2026-10-05', 'value' => 4]], 'total' => 4, 'tone' => 'info'],
                ],
            ]),
            Widget::metric('wrong-metric')->using(fn (): array => ['value' => 1, 'parts' => [['label' => 'A', 'value' => 1]], 'format' => ['style' => 'currency']]),
            Widget::metric('posts-metric')->resource('posts'),
            Widget::series('sources')->per('day', 'published_at')->sources([Source::make('posts', 'Posts')]),
            Widget::table('rows')->rows([Row::make('posts', 'Posts')]),
        ]);
        Gate::define('reports.view', fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 1);
        Panneau::pages([
            Page::dashboard('activity')->label('Activity')->period(['7d', '30d'])->widgets([
                Widget::stat('overdue')->using(RequestWidget::class),
                Widget::stat('where')->using(fn (WidgetRequest $request, ?Authenticatable $user): array => [
                    'value' => (int) $user?->getAuthIdentifier(),
                    'help' => $request->widget.' on '.$request->page.($request->period !== null ? ' for '.$request->period->option.' from '.$request->period->from.' to '.$request->period->to : ''),
                ]),
                Widget::stat('steady')->period(false)->using(fn (WidgetRequest $request): array => ['value' => $request->period === null ? 0 : 1]),
                Widget::stat('cached')->cache(60)->using(function (WidgetRequest $request): array {
                    $this->calls++;

                    return ['value' => $this->calls, 'help' => $request->period?->option];
                }),
                Widget::stat('drafts')->resource('posts', ['filter[status]' => 'draft']),
                Widget::metric('cost')->currency('USD')->goodWhen('down')->using(fn (WidgetRequest $request): array => [
                    'value' => 40,
                    'previous' => $request->period !== null ? 32 : null,
                    'help' => $request->period !== null ? 'from '.$request->period->previous()->from.' to '.$request->period->previous()->to : null,
                    'points' => [['date' => '2026-10-05', 'value' => 12], ['date' => '2026-10-06', 'value' => 28]],
                    'period' => 'day',
                    'parts' => [['label' => 'Summaries', 'value' => 30, 'key' => 'summaries'], ['label' => 'Translations', 'value' => 8]],
                    'other' => 2,
                ]),
            ]),
            Page::dashboard('reports')->can('reports.view')->widgets([Widget::stat('overdue')->using(OverdueWidget::class)]),
            Page::make('about', 'about'),
        ]);
    }

    public function test_the_data_of_a_widget_of_a_class_or_a_closure(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/overdue')
            ->assertOk()
            ->assertExactJson(['value' => 3, 'help' => 'Since Monday', 'tone' => 'danger', 'url' => '/panneau/resources/posts']);
        $this->getJson(self::API.'/dashboard/widgets/per-day')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonPath('points.1.value', 8);
        $this->getJson(self::API.'/dashboard/widgets/contests')
            ->assertOk()
            ->assertJsonPath('items.0.badge.tone', 'success');
    }

    public function test_the_empty_data_of_a_widget_of_a_plugin_is_an_object(): void
    {
        $this->assertSame('{}', $this->getJson(self::API.'/dashboard/widgets/map')->assertOk()->getContent());
    }

    public function test_a_widget_that_is_not_on_the_dashboard_of_the_user_is_a_404(): void
    {
        foreach (['nope', 'drafts'] as $id) {
            $this->getJson(self::API.'/dashboard/widgets/'.$id)
                ->assertNotFound()
                ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        }
        $this->getJson(self::API.'/dashboard/widgets/ada')->assertOk();
        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Grace']));
        $this->getJson(self::API.'/dashboard/widgets/ada')->assertNotFound();
    }

    public function test_a_widget_the_user_is_not_allowed_to_see_is_a_403(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/secret')
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_data_in_the_wrong_shape_is_a_500_with_the_wrong_keys_in_the_log(): void
    {
        Exceptions::fake();
        $this->app['config']->set('app.debug', false);

        $response = $this->getJson(self::API.'/dashboard/widgets/broken')
            ->assertStatus(500)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->assertStringNotContainsString('never sent', (string) $response->getContent());

        Exceptions::assertReported(fn (WidgetDataException $exception): bool => $exception->keys === ['value', 'tone']
            && $exception->getMessage() === 'The data of the dashboard widget "broken" doesn\'t have the shape of "stat": wrong value, tone.');
        // Errors are never kept
        $this->getJson(self::API.'/dashboard/widgets/broken')->assertStatus(500);
        $this->assertSame(2, $this->calls);
    }

    public function test_a_cached_widget_is_kept_per_user_and_locale(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/cached')->assertJsonPath('value', 1);
        $this->getJson(self::API.'/dashboard/widgets/cached')->assertJsonPath('value', 1);

        $this->getJson(self::API.'/dashboard/widgets/cached', ['Accept-Language' => 'fr'])->assertOk();
        $this->app->setLocale('fr');
        $this->getJson(self::API.'/dashboard/widgets/cached')->assertJsonPath('help', 'fr');
        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Grace']));
        $this->getJson(self::API.'/dashboard/widgets/cached')->assertJsonPath('value', $this->calls);
        $this->assertGreaterThanOrEqual(3, $this->calls);
    }

    public function test_a_widget_without_a_cache_runs_each_time(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/overdue')->assertOk();
        $this->getJson(self::API.'/dashboard/widgets/overdue')->assertOk();

        $this->assertSame(2, OverdueWidget::$calls);
    }

    public function test_guests_get_a_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::API.'/dashboard/widgets/overdue')->assertUnauthorized();
    }

    public function test_the_widgets_of_a_dashboard_page(): void
    {
        // The same id as a widget of the home page, another widget
        $this->getJson(self::API.'/dashboard/widgets/overdue?page=activity')
            ->assertOk()
            ->assertExactJson(['value' => 1, 'help' => 'overdue on activity']);
        $this->getJson(self::API.'/dashboard/widgets/where?page=activity')
            ->assertOk()
            ->assertExactJson(['value' => 1, 'help' => 'where on activity']);
        $this->getJson(self::API.'/dashboard/widgets/overdue')->assertOk()->assertJsonPath('help', 'Since Monday');
        $this->getJson(self::API.'/dashboard/widgets/overdue?page=home')->assertOk()->assertJsonPath('help', 'Since Monday');
        $this->getJson(self::API.'/dashboard/widgets/overdue?page=reports')->assertOk();
    }

    public function test_a_widget_that_is_not_on_that_page_of_the_user_is_a_404(): void
    {
        foreach ([
            'per-day?page=activity', // a widget of the home page
            'where', // a widget of another page
            'where?page=nope', // an unknown page
            'where?page=about', // a page that isn't a dashboard
            'drafts?page=activity', // a widget on a resource
        ] as $path) {
            $this->getJson(self::API.'/dashboard/widgets/'.$path)
                ->assertNotFound()
                ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        }
        // A page the user can't view
        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Grace']));
        $this->getJson(self::API.'/dashboard/widgets/overdue?page=reports')->assertNotFound();
    }

    public function test_an_empty_page_is_a_422(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/where?page=')
            ->assertUnprocessable()
            ->assertJsonPath('type', Problem::VALIDATION)
            ->assertJsonPath('errors.page.0', 'The page must not be empty.');
    }

    public function test_a_cached_widget_is_kept_per_page(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/cached?page=activity')->assertJsonPath('value', 1);
        $this->getJson(self::API.'/dashboard/widgets/cached?page=activity')->assertJsonPath('value', 1);
        // The widget of the same id on the home page
        $this->getJson(self::API.'/dashboard/widgets/cached')->assertJsonPath('value', 2);
        $this->getJson(self::API.'/dashboard/widgets/cached?page=activity')->assertJsonPath('value', 1);
    }

    public function test_a_breakdown_and_a_stat_with_a_target_of_their_own(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/states')
            ->assertOk()
            ->assertJsonPath('items.0.tone', 'success')
            ->assertJsonPath('other', 1);
        $this->getJson(self::API.'/dashboard/widgets/budget')->assertOk()->assertJsonPath('target', 100);
    }

    public function test_a_breakdown_in_the_wrong_shape_is_a_500(): void
    {
        Exceptions::fake();
        $this->app['config']->set('app.debug', false);

        $this->getJson(self::API.'/dashboard/widgets/wrong-parts')->assertStatus(500);

        Exceptions::assertReported(fn (WidgetDataException $exception): bool => $exception->keys === ['items.0.label', 'items.0.value']);
    }

    public function test_a_widget_of_a_dashboard_page_gets_its_period(): void
    {
        $period = 'period[option]=30d&period[from]=2026-10-19&period[to]=2026-11-17';
        $this->getJson(self::API.'/dashboard/widgets/where?page=activity&'.$period)
            ->assertOk()
            ->assertJsonPath('help', 'where on activity for 30d from 2026-10-19 to 2026-11-17');
        $this->getJson(self::API.'/dashboard/widgets/steady?page=activity&'.$period)->assertOk()->assertJsonPath('value', 0);
        // The home page has no period: its widgets get none
        $this->getJson(self::API.'/dashboard/widgets/home-period?'.$period)->assertOk()->assertJsonPath('value', 0);
    }

    public function test_a_cached_widget_is_kept_per_period(): void
    {
        $url = self::API.'/dashboard/widgets/cached?page=activity&period[option]=7d&period[from]=2026-11-11&period[to]=2026-11-17';
        $this->getJson($url)->assertJsonPath('value', 1)->assertJsonPath('help', '7d');
        $this->getJson($url)->assertJsonPath('value', 1);
        $this->getJson(self::API.'/dashboard/widgets/cached?page=activity&period[option]=30d&period[from]=2026-10-19&period[to]=2026-11-17')
            ->assertJsonPath('value', 2)
            ->assertJsonPath('help', '30d');
    }

    public function test_a_metric_compares_its_period_with_the_previous_one(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/cost?page=activity&period[option]=30d&period[from]=2026-10-20&period[to]=2026-11-18')
            ->assertOk()
            ->assertJsonPath('value', 40)
            ->assertJsonPath('previous', 32)
            ->assertJsonPath('help', 'from 2026-09-20 to 2026-10-19')
            ->assertJsonPath('parts.1.label', 'Translations');
    }

    public function test_a_table_and_a_series_of_several_lines_of_their_own(): void
    {
        $response = $this->getJson(self::API.'/dashboard/widgets/costs')
            ->assertOk()
            ->assertJsonPath('rows.0.cells.calls.help', 'failed')
            ->assertJsonMissingPath('format');
        // A row without cells has them as an object
        $this->assertStringContainsString('"label":"Translations","cells":{}', (string) $response->getContent());
        $this->getJson(self::API.'/dashboard/widgets/messages')
            ->assertOk()
            ->assertJsonPath('series.1.label', 'Text messages')
            ->assertJsonPath('series.0.alert.value', 1);
    }

    public function test_combined_widgets_that_read_resources_have_no_data(): void
    {
        foreach (['posts-metric', 'sources', 'rows'] as $id) {
            $this->getJson(self::API.'/dashboard/widgets/'.$id)
                ->assertNotFound()
                ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        }
    }

    public function test_a_metric_in_the_wrong_shape_is_a_500(): void
    {
        Exceptions::fake();
        $this->app['config']->set('app.debug', false);

        $this->getJson(self::API.'/dashboard/widgets/wrong-metric')->assertStatus(500);

        Exceptions::assertReported(fn (WidgetDataException $exception): bool => $exception->keys === ['format.currency']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wrongPeriods(): array
    {
        return [
            'an unknown option' => ['period[option]=decade&period[from]=2026-01-01&period[to]=2026-12-31'],
            'no days' => ['period[option]=7d'],
            'a day that does not exist' => ['period[option]=month&period[from]=2026-02-01&period[to]=2026-02-30'],
            'from after to' => ['period[option]=7d&period[from]=2026-11-17&period[to]=2026-11-11'],
        ];
    }

    #[DataProvider('wrongPeriods')]
    public function test_a_wrong_period_is_a_422(string $query): void
    {
        $this->getJson(self::API.'/dashboard/widgets/where?page=activity&'.$query)
            ->assertUnprocessable()
            ->assertJsonPath('type', Problem::VALIDATION)
            ->assertJsonStructure(['errors' => ['period']]);
    }
}
