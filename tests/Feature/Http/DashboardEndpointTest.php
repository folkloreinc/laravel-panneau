<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Exceptions;
use Panneau\Dashboard\Widget;
use Panneau\Exceptions\WidgetDataException;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;
use TestApp\Dashboard\OverdueWidget;

/**
 * `GET /dashboard/widgets/{widget}` (panneau-js decision 0064).
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
}
