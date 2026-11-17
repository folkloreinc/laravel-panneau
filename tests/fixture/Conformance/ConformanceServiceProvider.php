<?php

namespace TestApp\Conformance;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Panneau\Context\ContextParam;
use Panneau\Dashboard\Widget;
use Panneau\Navigation\MenuItem;
use Panneau\Navigation\Page;
use Panneau\Support\Facade as Panneau;
use TestApp\Dashboard\OverdueWidget;
use TestApp\Models\Asset;
use TestApp\Models\Author;
use TestApp\Models\Post;
use TestApp\Models\Run;
use TestApp\Models\User;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\ImagesResource;
use TestApp\Panneau\Resources\RunsResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Policies\AssetPolicy;
use TestApp\Policies\AuthorPolicy;
use TestApp\Policies\PostPolicy;
use TestApp\Policies\RunPolicy;

/**
 * The app served to the protocol's conformance kit in CI: the fixture resources, a media library,
 * direct uploads, pages and a main menu, a dashboard and a dashboard page, a context with an optional param, the password reset
 * and the email verification of the `panneau` auth mode, behind a bearer token (`Authorization:
 * Bearer conformance`).
 */
class ConformanceServiceProvider extends ServiceProvider
{
    public const TOKEN = 'conformance';

    public function register(): void
    {
        $config = $this->app['config'];
        $config->set('auth.providers.conformance', ['driver' => 'eloquent', 'model' => User::class]);
        $config->set('auth.guards.conformance', ['driver' => 'conformance-token', 'provider' => 'conformance']);
        $config->set('auth.passwords.conformance', ['provider' => 'conformance', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]);
        $config->set('panneau.auth.guard', 'conformance');
        // The checks of the password reset and of the email verification (panneau-js decision
        // 0048), with the skeleton's users: the kit doesn't log in, so the API keeps its token
        $config->set('panneau.auth.mode', 'panneau');
        $config->set('panneau.auth.password_reset', true);
        $config->set('panneau.auth.email_verification', true);
        // Two-factor authentication: the kit reads the status, and sends a challenge without a login
        $config->set('panneau.auth.two_factor', true);
        // Passkeys: the kit asks for the options of a login, sends a credential that doesn't
        // exist, and lists the passkeys of the account
        $config->set('panneau.auth.passkeys', true);
        $config->set('passkeys.relying_party_id', 'localhost');
        $config->set('passkeys.allowed_origins', ['http://localhost']);
        // An OAuth provider: the kit checks that its login starts with a redirection
        $config->set('panneau.auth.providers', ['google' => ['label' => 'Google']]);
        $config->set('services.google', ['client_id' => 'conformance', 'client_secret' => 'conformance', 'redirect' => null]);
        $config->set('panneau.auth.passwords', 'conformance');
        // A token, not a session: no "web" middleware, so no CSRF token to send
        $config->set('panneau.middleware', ['panneau.auth']);
        $config->set('panneau.locales', ['fr', 'en']);
        // Signing needs no request to the storage, so a bucket that doesn't exist will do
        $config->set('filesystems.disks.uploads', [
            'driver' => 's3',
            'key' => 'conformance',
            'secret' => 'conformance',
            'region' => 'us-east-1',
            'bucket' => 'panneau-conformance',
            'endpoint' => 'https://storage.test',
            'use_path_style_endpoint' => true,
        ]);
        $config->set('panneau.uploads.enabled', true);
        $config->set('panneau.uploads.disk', 'uploads');
    }

    public function boot(): void
    {
        Auth::viaRequest('conformance-token', fn (Request $request): ?GenericUser => $request->bearerToken() === self::TOKEN
            ? new GenericUser(['id' => 1, 'name' => 'Conformance'])
            : null);
        Gate::define('viewPanneau', fn ($user): bool => true);
        Gate::define('uploadPanneauFiles', fn ($user): bool => true);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Author::class, AuthorPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Run::class, RunPolicy::class);

        // The runs: a log with aggregates (panneau-js decision 0084), which the kit counts, sums
        // and groups
        Panneau::resources([ConformancePostsResource::class, AuthorsResource::class, TagsResource::class, ImagesResource::class, RunsResource::class]);
        Panneau::pages([
            Page::make('reports', 'reports')->label('Reports'),
            // A dashboard page with a period (panneau-js decision 0084): the kit asks for its
            // widgets with `?page=`, and for those with data of their own with the default period
            Page::dashboard('activity')->label('Activity')->period(['7d', '30d', '90d', 'month', 'year'], default: '30d')->widgets([
                Widget::stat('published')->label('Published')->resource(ConformancePostsResource::class, ['filter[status]' => 'published']),
                Widget::stat('overdue')->label('Overdue')->using(OverdueWidget::class),
                Widget::series('runs-in-period')->label('Runs')->resource(RunsResource::class)->per('day', 'ran_at')->period('ran_at'),
            ]),
        ]);
        Panneau::menu('main', [
            MenuItem::resources(),
            MenuItem::separator(),
            MenuItem::group('More', [MenuItem::page('activity'), MenuItem::page('reports'), MenuItem::link('Site', '/')->external()]),
        ]);
        // The dashboard (panneau-js decision 0064): the kit checks the data of the widgets with
        // data of their own, the lists of those on a resource, and a widget that isn't on it
        Panneau::dashboard([
            Widget::stat('drafts')->label('Drafts')->resource(ConformancePostsResource::class, ['filter[status]' => 'draft']),
            Widget::list('latest')->label('Latest posts')->resource(ConformancePostsResource::class, ['sort' => '-published_at']),
            Widget::stat('overdue')->label('Overdue')->using(OverdueWidget::class),
            // On the aggregates of a resource: the kit reads them with the query of each widget
            Widget::series('runs-per-day')->label('Runs per day')->resource(RunsResource::class)->per('day', 'ran_at'),
            Widget::breakdown('runs-by-status')->label('Runs by status')->resource(RunsResource::class)->by('status'),
            Widget::stat('runs-cost')->label('Cost')->resource(RunsResource::class)->sum('amount')->target(100000),
            Widget::breakdown('contests-by-state')->label('Contests by state')->using(fn (): array => [
                'items' => [['label' => 'Open', 'value' => 3, 'tone' => 'success'], ['label' => 'Closed', 'value' => 1]],
                'total' => 4,
            ]),
            Widget::series('per-day')->label('Posts per day')->cache(60)->using(fn (): array => [
                'period' => 'day',
                'points' => [['date' => '2026-10-05', 'value' => 4], ['date' => '2026-10-06', 'value' => 8]],
                'total' => 12,
            ]),
            Widget::list('contests')->label('Open contests')->using(fn (): array => [
                'items' => [['title' => 'Spring contest', 'meta' => '12 entries', 'badge' => ['label' => 'Open', 'tone' => 'success']]],
            ]),
            Widget::make('weather', 'weather')->settings(['city' => 'Montréal'])->using(fn (): array => ['sky' => 'clear']),
        ]);
        // The resources don't read it: the kit checks its choices, a definition built for a
        // value, and a value that doesn't exist refused
        Panneau::context([
            ContextParam::make('season')->label('Season')->allLabel('All seasons')
                ->options(['2026' => 'Season 2026', '2025' => 'Season 2025']),
        ]);
        Panneau::routes();
    }
}
