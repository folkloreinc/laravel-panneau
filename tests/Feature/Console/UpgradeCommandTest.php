<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Panneau\Tests\TestCase;
use Panneau\Upgrade\Audit;
use Panneau\Upgrade\Codebase;
use Panneau\Upgrade\Finding;

/**
 * `panneau:install --upgrade [--check]` on fixtures shaped like 1.3 and 1.4 projects
 * (tests/fixture/upgrade).
 */
class UpgradeCommandTest extends TestCase
{
    private string $project;

    protected function tearDown(): void
    {
        if (isset($this->project)) {
            (new Filesystem)->deleteDirectory($this->project);
        }

        parent::tearDown();
    }

    /**
     * Copies a fixture project and makes it the app.
     */
    private function project(string $fixture): void
    {
        $this->project = sys_get_temp_dir().'/panneau-upgrade-'.bin2hex(random_bytes(4));
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->project);
        if ($fixture !== '') {
            $files->copyDirectory(__DIR__.'/../../fixture/upgrade/'.$fixture, $this->project);
        }
        $this->app->setBasePath($this->project);
        $this->app->useAppPath($this->project.'/app');
        $this->app->useConfigPath($this->project.'/config');
        $this->app->useBootstrapPath($this->project.'/bootstrap');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function panneau(array $options, int $status = 0): string
    {
        $this->withoutMockingConsoleOutput();
        $exit = Artisan::call('panneau:install', ['--no-interaction' => true, ...$options]);
        $output = Artisan::output();
        $this->assertSame($status, $exit, $output);

        return $output;
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->project.'/'.$path);
    }

    private function audit(): Audit
    {
        return new Audit(new Codebase($this->project, new Filesystem));
    }

    private function finding(string $section, string $subject): Finding
    {
        $finding = $this->audit()->section($section)->find($subject);
        $this->assertNotNull($finding, sprintf('No "%s" in %s.', $subject, $section));

        return $finding;
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(): array
    {
        $files = [];
        foreach ((new Filesystem)->allFiles($this->project, true) as $file) {
            $files[$file->getRelativePathname()] = (string) md5_file($file->getPathname());
        }
        ksort($files);

        return $files;
    }

    public function test_the_check_writes_nothing(): void
    {
        $this->project('v1.4');
        $before = $this->snapshot();

        $output = $this->panneau(['--upgrade' => true, '--check' => true]);

        $this->assertSame($before, $this->snapshot());
        $this->assertStringContainsString('Resources', $output);
        $this->assertStringContainsString('to move by hand (manual)', $output);
    }

    public function test_the_check_reads_the_config_and_routes_of_a_1_4_project(): void
    {
        $this->project('v1.4');
        $audit = $this->audit();

        $this->assertSame('1.x', $audit->configShape());
        $this->assertSame('1.x', $audit->routesShape());
        $this->assertSame([
            'App\\Panneau\\Resources\\Pages',
            'App\\Panneau\\Resources\\Projects',
            'App\\Panneau\\Resources\\JobListings',
            'App\\Panneau\\Resources\\Blocks',
        ], $audit->resources());
        $this->assertNull($audit->guard());
        $this->assertTrue($audit->usesPanneauLogin());
        $this->assertSame(['App\\Panneau\\PanneauServiceProvider'], array_map(fn ($provider) => $provider->name, $audit->providers()));

        $this->assertSame('locales', $this->finding('config', "intl.locales: ['fr', 'en']")->target);
        $this->assertSame('path', $this->finding('config', "routes.prefix: 'panneau'")->target);
        $this->assertStringStartsWith('kept', $this->finding('config', "analytics_id: env('PANNEAU_ANALYTICS_ID')")->target);
        $this->assertSame('mode external, on the default guard', $this->finding('config', 'auth')->target);

        $this->assertTrue($this->finding('routes', 'Panneau::router()->auth(): the login page of Panneau')->isManual());
        $this->assertTrue($this->finding('routes', 'GET /pages/sections → PagesController@sections')->isManual());
        $this->assertSame('nothing: the page of the app (panneau.app)', $this->finding('routes', 'GET / → HomeController@index')->target);
        $this->assertSame('the viewPanneau gate', $this->finding('routes', 'middleware can:view,Panneau\\Panneau')->target);
    }

    public function test_the_check_maps_resources_and_their_lists(): void
    {
        $this->project('v1.4');

        $pages = $this->finding('resources', 'Pages (pages)');
        $this->assertSame('app/Panneau/Resources/Pages.php:15', $pages->location);
        $this->assertSame('repository()', $pages->detail('$repository = App\\Contracts\\Repositories\\Pages')?->target);
        $this->assertSame('toItem(), which resolves the JsonResource', $pages->detail('$jsonResource = App\\Http\\Resources\\PageResource')?->target);
        $this->assertStringStartsWith('manual: types()', (string) $pages->detail('$types: 2 types')?->target);
        $this->assertSame('nothing: the default', $pages->detail("\$settings['hideInNavbar'] = false")?->target);

        $index = $pages->detail('index()');
        $this->assertNotNull($index);
        $this->assertSame('$list->columns([...]) of the fields', $index->detail('columns: title, type, sites, published')?->target);
        $this->assertSame('$list->sorts([...])', $index->detail('sortable: title')?->target);
        $this->assertSame('RelationFilter on a resource', $index->detail('filter site_id: select')?->target);
        $this->assertSame('DuplicateAction (not built yet)', $index->detail("actionsProps: ['hasDuplicateRoute' => true]")?->target);
        $this->assertTrue((bool) $index->detail('column sites: component site-badges')?->isManual());

        // 1.x ids are camel case, 5.x ids kebab case
        $jobs = $this->finding('resources', 'JobListings (jobListings)');
        $this->assertStringStartsWith('job-listings in 5.x', (string) $jobs->detail('id jobListings')?->target);
        $this->assertNull($pages->detail('id pages'));
        $this->assertTrue((bool) $jobs->detail("\$settings['hideInNavbar'] = true")?->isManual());

        $this->assertStringStartsWith('manual: a BlockType', $this->finding('types', 'TextBlock (text) of Blocks')->target);
        $this->assertTrue($this->finding('types', 'Page (page) of Pages')->isManual(), 'A type that other types extend is still a type when a resource lists it.');
        $home = $this->finding('types', 'Home (home) of Pages');
        $this->assertSame('the create ability of the policy', $home->detail("\$settings['canCreate'] = false")?->target);
        $this->assertNull($this->audit()->section('types')->find('Block'), 'Abstract types are left out.');
    }

    public function test_the_check_groups_field_classes_by_their_1x_base(): void
    {
        $this->project('v1.4');

        $this->assertSame('Repeater', $this->finding('fields', 'Credits extends Items (items)')->target);
        $this->assertSame('Group', $this->finding('fields', 'Credit extends Fields (fields)')->target);
        $this->assertSame('Relation', $this->finding('fields', 'Client extends ResourceItem (resource-item)')->target);
        $this->assertSame('Relation->multiple()', $this->finding('fields', 'Projects extends ResourceItems (items)')->target);
        $this->assertSame('RichText->localized()', $this->finding('fields', 'HtmlLocalized extends LocalizedField (localized)')->target);
        $this->assertSame('RichText', $this->finding('fields', 'Html extends Panneau\\Fields\\Html (html)')->target);
        $this->assertSame('Select', $this->finding('fields', 'Theme extends Radios (radios)')->target);
        $this->assertTrue($this->finding('fields', 'Sites extends Field (items-resource)')->isManual());
        $image = $this->finding('fields', 'Image extends Panneau\\Fields\\Image (image-with-credits)');
        $this->assertSame('manual: Image; its component image-with-credits needs a JS plugin', $image->target);

        $this->assertSame('Text->localized() (Textarea with isTextarea())', $this->finding('field-uses', 'TextLocalized ×5 (localized)')->target);
        $this->assertStringContainsString('PageSlug field of laravel-folklore 2', $this->finding('field-uses', 'PageSlugLocalized ×1 (page-slug-localized)')->target);
    }

    public function test_the_check_lists_controllers_providers_and_files_to_delete(): void
    {
        $this->project('v1.4');

        $pages = $this->finding('controllers', 'PagesController of Pages');
        $this->assertSame('scope(), or the filters of list()', $pages->detail('getIndexQueryFromRequest()')?->target);
        $this->assertTrue((bool) $pages->detail('sections()')?->isManual());
        $this->assertSame('creating()', $this->finding('controllers', 'ProjectsController of Projects')->detail('getStoreDataFromRequest()')?->target);

        $provider = $this->finding('providers', 'PanneauServiceProvider');
        $this->assertSame("'locales' in config/panneau.php", $provider->detail('LocalizedField::setLocalesResolver()')?->target);
        $this->assertNotNull($provider->detail('Upload::setEndpoint()'));
        $this->assertNotNull($provider->detail('PageSlug::setRoutesResolver()'));
        $this->assertNotNull($provider->detail('Gate::policy(Panneau::class, …)'));
        $this->assertSame('config/app.php:9', $this->finding('providers', 'PanneauServiceProvider registered in config/app.php')->location);

        $files = $this->audit()->generatedFiles();
        foreach ([
            'app/Panneau/Http/Controllers/HomeController.php',
            'app/Panneau/Http/Controllers/AccountController.php',
            'app/Panneau/Http/Controllers/UploadController.php',
            'app/Panneau/Http/Controllers/Controller.php',
            'app/Panneau/Http/Requests/UploadRequest.php',
            'app/Panneau/Policies/PanneauPolicy.php',
            'app/Panneau/Composers/AppComposer.php',
            'app/Panneau/PanneauDefinition.php',
            'app/Panneau/PanneauServiceProvider.php',
            'resources/views/vendor/panneau/layout.blade.php',
        ] as $file) {
            $this->assertContains($file, $files);
        }
        // The site's own controller of the same name stays
        $this->assertNotContains('app/Http/Controllers/HomeController.php', $files);

        $this->assertSame('manual: the 5.x routes are panneau.app and panneau.api.*', $this->finding('route-names', "route('panneau.resources.pages.index')")->target);
        $this->assertSame("manual: \$request->routeIs('panneau.api.resources.index') (5.x has no such macro)", $this->finding('macros', 'isPanneauIndex()')->target);
        $this->assertSame('app/Http/Resources/PageResource.php:14', $this->finding('macros', 'isPanneauIndex()')->location);
        $this->assertTrue($this->finding('macros', 'panneau_route()')->isManual());
    }

    public function test_the_check_reads_the_build_and_the_deployment(): void
    {
        $this->project('v1.4');

        $this->assertSame('folklore/laravel-panneau ^5.0@alpha', $this->finding('packages', 'folklore/laravel-panneau ~1.4.2')->target);
        $this->assertStringStartsWith('folklore/laravel-folklore ^2.0', $this->finding('packages', 'folklore/laravel-folklore ^1.1.8')->target);
        $this->assertStringStartsWith('removed', $this->finding('packages', '@panneau/core ^4.1.1')->target);
        $this->assertStringStartsWith('Vite builds the admin next to it', $this->finding('deployment', 'package.json: the site builds with @folklore/cli (webpack)')->target);
        $envoy = $this->finding('deployment', 'Envoy.blade.php copies built files from public/');
        $this->assertSame("manual: add public/build/, Vite's build of the admin", $envoy->target);
        $this->assertSame('Envoy.blade.php:6', $envoy->location);
        $this->assertNotNull($this->audit()->section('deployment')->find('.gitignore'));
        $this->assertNull($this->audit()->section('deployment')->find('node-version 24'));
    }

    public function test_the_check_reads_a_1_3_project(): void
    {
        $this->project('v1.3');
        $audit = $this->audit();

        $this->assertSame('admin', $audit->guard());
        $this->assertSame('mode external, on the guard admin', $this->finding('config', 'auth')->target);
        $this->assertSame('nothing: the 5.x routes are named panneau.app and panneau.api.*', $this->finding('routes', 'route names with panneau.routes.name_prefix')->target);
        $this->assertTrue($this->finding('routes', 'MATCH medias/batch/delete → OrdersController@batchDelete')->isManual());
        $this->assertTrue($this->finding('route-names', "route('admin.orders.export')")->isManual());

        // Fields that extend the project's own classes are grouped by the 1.x class at the end
        $this->assertTrue($this->finding('fields', 'Customer extends ResourceItem → Field (item)')->isManual());
        $this->assertTrue($this->finding('fields', 'ImageField extends ImageUpload (custom_upload)')->isManual());
        $this->assertTrue($this->finding('field-uses', 'Password ×1 (password)')->isManual());

        $orders = $this->finding('resources', 'Orders (orders)');
        $this->assertTrue((bool) $orders->detail('settings()')?->isManual());
        $this->assertSame('actions(): LinkAction, or actions of the resource', $orders->detail('index()')?->detail('actions: 1 buttons')?->target);
        $this->assertTrue((bool) $this->finding('resources', 'Pages (pages)')->detail('index()')?->detail('the index is built at runtime')?->isManual());
        $this->assertTrue((bool) $this->finding('resources', 'Users (users)')->detail("\$roles = ['admin', 'editor']")?->isManual());

        // The 1.x provider is where the 5.x one goes; the policy is in AuthServiceProvider
        $this->assertSame('replaced by the 5.x provider (--upgrade, with a backup)', $this->finding('providers', 'PanneauServiceProvider')->target);
        $this->assertNotNull($this->finding('providers', 'AuthServiceProvider')->detail('$policies[Panneau::class]'));
        $this->assertTrue($this->finding('providers', 'AuthServiceProvider')->isManual());
        $this->assertNotContains('app/Providers/PanneauServiceProvider.php', $audit->generatedFiles());
        $this->assertContains('lang/vendor/panneau', $audit->generatedFiles());

        $this->assertTrue($this->finding('deployment', 'node-version 18')->isManual());
        $this->assertSame('app/Http/Responses/LoginResponse.php:11', $this->finding('macros', 'isPanneau()')->location);
    }

    public function test_the_check_lists_the_1x_contracts_outside_the_resources(): void
    {
        $this->project('v1.3');

        // Entity contracts of the site that extend ResourceItem break the site once 5.x is installed
        $order = $this->finding('contracts', 'Order extends ResourceItem');
        $this->assertSame('app/Contracts/Entities/Order.php:8', $order->location);
        $this->assertSame('manual: remove it: Entity, which it extends too, declares id()', $order->target);
        $store = $this->finding('contracts', 'Store extends ResourceItem');
        $this->assertSame('app/Contracts/Entities/Store.php:5', $store->location);
        $this->assertStringStartsWith('manual: Folklore\\Contracts\\Entities\\Entity', $store->target);
        // Other references, but not the imports
        $this->assertSame('app/Http/Resources/OrderResource.php:13', $this->finding('contracts', 'Panneau\\Contracts\\ResourceItem')->location);
        $this->assertCount(3, $this->audit()->section('contracts')->findings);

        $output = $this->panneau(['--upgrade' => true, '--no-js' => true]);
        $this->assertStringContainsString('Uses of 1.x contracts', $output);
        $this->assertStringContainsString('app/Contracts/Entities/Order.php:8', $output);
    }

    public function test_the_check_skips_the_branches_of_the_workflows(): void
    {
        $this->project('v1.3');

        // A branch named after Panneau, in the triggers, depends on nothing
        $this->assertNull($this->audit()->section('deployment')->find('- feature/13-panneau-5'));
        $this->assertNull($this->audit()->section('deployment')->find('branches: [develop, feature/13-panneau-5]'));
        $this->assertSame('.github/workflows/checks.yml:16', $this->finding('deployment', '- run: php artisan panneau:assets')->location);
    }

    public function test_the_check_lists_what_the_rector_rules_left(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Panneau/Resources/Tags.php', <<<'PHP'
            <?php

            namespace App\Panneau\Resources;

            class Tags
            {
                // TODO(panneau-5): canCreate is false: the create() of the policy denies it
            }
            PHP);

        $finding = $this->finding('todos', 'canCreate is false: the create() of the policy denies it');

        $this->assertSame('app/Panneau/Resources/Tags.php:7', $finding->location);
        $this->assertTrue($finding->isManual());
    }

    public function test_the_check_after_the_upgrade_and_the_rector_rules(): void
    {
        $this->project('v1.4');
        $this->panneau(['--upgrade' => true]);
        $root = dirname(__DIR__, 3);

        exec(sprintf(
            'cd %s && %s %s process app/Panneau --config %s --no-progress-bar --no-diffs 2>&1',
            escapeshellarg($this->project),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root.'/vendor/bin/rector'),
            escapeshellarg($root.'/rector.php'),
        ), $rector, $status);
        $this->assertSame(0, $status, implode("\n", $rector));
        $this->assertStringContainsString('Text::make(\'title\')->localized()', $this->read('app/Panneau/Resources/Projects.php'));

        $output = $this->panneau(['--upgrade' => true, '--check' => true]);

        $this->assertStringContainsString('Left by the Rector rules', $output);
        $this->assertStringContainsString("'published' was hidden from the form", $output);
        // What the rules leave on 1.x parents, and the rest of the project, is still listed
        $this->assertStringContainsString('Credits extends Items (items)', $output);
        $this->assertStringContainsString('isPanneauIndex()', $output);
        // types() is read like $types
        $this->assertNotNull($this->audit()->section('types')->find('TextBlock (text) of Blocks'));
        $this->assertSame('a 5.x Resource', $this->finding('resources', 'Pages (pages)')->target);
        $this->assertPhpFilesAreValid();
    }

    public function test_the_upgrade_converts_a_1_4_project(): void
    {
        $this->project('v1.4');
        $config1x = $this->read('config/panneau.php');
        $routes1x = $this->read('routes/panneau.php');

        $output = $this->panneau(['--upgrade' => true]);

        $config = require $this->project.'/config/panneau.php';
        $this->assertSame('Example Agency', $config['name']);
        $this->assertSame(['fr', 'en'], $config['locales']);
        $this->assertSame('panneau', $config['path']);
        $this->assertSame(['web', 'panneau.auth'], $config['middleware']);
        $this->assertSame(['mode' => 'external', 'guard' => null, 'login_url' => '/login'], $config['auth']);
        $this->assertSame([], $config['resources']);
        $this->assertArrayHasKey('analytics_id', $config);
        $this->assertArrayNotHasKey('routes', $config);
        $this->assertArrayNotHasKey('intl', $config);
        $this->assertStringContainsString("'analytics_id' => env('PANNEAU_ANALYTICS_ID'),", $this->read('config/panneau.php'));
        $this->assertSame($config1x, $this->read('config/panneau.php.1x.bak'));

        $provider = $this->read('app/Providers/PanneauServiceProvider.php');
        $this->assertStringContainsString("            \\App\\Panneau\\Resources\\Pages::class,\n            \\App\\Panneau\\Resources\\Projects::class,\n", $provider);
        $this->assertStringContainsString('return false;', $provider);
        $this->assertStringContainsString("        \\App\\Providers\\PanneauServiceProvider::class,\n    ],", $this->read('config/app.php'));
        $this->assertStringNotContainsString('App\\Panneau\\PanneauServiceProvider', $this->read('config/app.php'));

        $this->assertStringContainsString('Panneau::routes();', $this->read('routes/panneau.php'));
        $this->assertSame($routes1x, $this->read('routes/panneau.php.1x.bak'));
        // The 1.x page is moved aside, so the package's page is used
        $this->assertFileDoesNotExist($this->project.'/resources/views/vendor/panneau/app.blade.php');
        $this->assertStringContainsString('$props', $this->read('resources/views/vendor/panneau/app.blade.php.1x.bak'));

        // Vite builds the admin next to the site's bundler
        $this->assertStringContainsString('createPanneauApp', $this->read('resources/js/panneau.tsx'));
        $this->assertStringContainsString("input: ['resources/js/panneau.tsx']", $this->read('vite.config.mjs'));
        $package = json_decode($this->read('package.json'), true);
        $this->assertSame('^5.0.0-alpha.0', $package['dependencies']['@panneau/app']);
        $this->assertSame('^4.1.1', $package['dependencies']['@panneau/core']);
        $this->assertSame('^8.0.0', $package['devDependencies']['vite']);
        $this->assertSame('^3.0.0', $package['devDependencies']['laravel-vite-plugin']);
        $this->assertSame('vite build', $package['scripts']['build:panneau']);
        $this->assertSame('npm run clean && npm run build:scripts && npm run build:views && npm run build:panneau', $package['scripts']['build']);
        $this->assertArrayNotHasKey('vite', json_decode($this->read('package.json.1x.bak'), true)['devDependencies']);

        // The files to delete are listed, and kept without a confirmation
        $this->assertStringContainsString('app/Panneau/Http/Controllers/HomeController.php', $output);
        $this->assertFileExists($this->project.'/app/Panneau/Http/Controllers/HomeController.php');
        $this->assertStringContainsString('to move by hand', $output);
        $this->assertStringContainsString("Set 'auth.login_url'", $output);
        $this->assertPhpFilesAreValid();
    }

    public function test_the_upgrade_can_run_again(): void
    {
        $this->project('v1.4');
        $this->panneau(['--upgrade' => true]);
        $after = $this->snapshot();

        $output = $this->panneau(['--upgrade' => true]);

        $this->assertSame($after, $this->snapshot());
        $this->assertStringContainsString('KEPT (already 5.x)', $output);
        $this->assertStringNotContainsString('REFUSED', $output);
    }

    public function test_the_upgrade_never_replaces_a_backup_without_force(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/config/panneau.php.1x.bak', "<?php\n\nreturn ['older' => true];\n");
        $config1x = $this->read('config/panneau.php');

        $output = $this->panneau(['--upgrade' => true]);

        $this->assertStringContainsString('REFUSED', $output);
        $this->assertSame($config1x, $this->read('config/panneau.php'));
        $this->assertStringContainsString("'older' => true", $this->read('config/panneau.php.1x.bak'));

        $this->panneau(['--upgrade' => true, '--force' => true]);

        // The older backup stays, and the 1.x config gets the next one
        $this->assertStringContainsString("'older' => true", $this->read('config/panneau.php.1x.bak'));
        $this->assertSame($config1x, $this->read('config/panneau.php.1x.bak.2'));
        $this->assertArrayHasKey('path', require $this->project.'/config/panneau.php');
    }

    public function test_a_forced_run_after_the_upgrade_loses_nothing(): void
    {
        $this->project('v1.4');
        $config1x = $this->read('config/panneau.php');
        $routes1x = $this->read('routes/panneau.php');
        $this->panneau(['--upgrade' => true]);
        $provider = $this->read('app/Providers/PanneauServiceProvider.php');

        $this->panneau(['--upgrade' => true, '--force' => true]);

        $this->assertSame($config1x, $this->read('config/panneau.php.1x.bak'));
        $this->assertSame($routes1x, $this->read('routes/panneau.php.1x.bak'));
        // The config is 5.x: the provider, which has the resources, isn't written again
        $this->assertSame($provider, $this->read('app/Providers/PanneauServiceProvider.php'));
        $this->assertStringContainsString('\\App\\Panneau\\Resources\\Pages::class', $provider);
    }

    public function test_the_check_still_reports_after_the_upgrade(): void
    {
        $this->project('v1.4');
        $this->panneau(['--upgrade' => true]);

        $output = $this->panneau(['--upgrade' => true, '--check' => true]);

        $this->assertStringNotContainsString('Nothing of Panneau 1.x', $output);
        $this->assertStringContainsString('Credits extends Items (items)', $output);
        $this->assertStringContainsString('PagesController of Pages', $output);
        $this->assertStringContainsString('isPanneauIndex()', $output);
    }

    public function test_the_check_reads_the_provider_that_the_upgrade_writes(): void
    {
        $this->project('v1.4');
        $this->panneau(['--upgrade' => true]);

        // The 5.x provider registers the resources: nothing in it is 1.x
        foreach ($this->audit()->section('providers')->findings as $finding) {
            $this->assertStringStartsNotWith('app/Providers/PanneauServiceProvider.php', (string) $finding->location, $finding->subject.': '.$finding->target);
        }
        $this->assertSame('a 5.x Resource', $this->finding('resources', 'Pages (pages)')->target);
        $this->assertSame('a 5.x Resource', $this->finding('resources', 'Blocks (blocks)')->target);
    }

    public function test_the_upgrade_filters_the_middleware_of_the_1x_config(): void
    {
        $this->project('v1.4');
        $config = str_replace(
            "'middleware' => [\\Panneau\\Http\\Middleware\\DispatchHandlingRequestEvent::class],",
            "'middleware' => ['web', 'auth:editors', 'can:view,' . \\Panneau\\Panneau::class, \\App\\Http\\Middleware\\SetLocale::class],",
            $this->read('config/panneau.php'),
        );
        $this->assertStringContainsString('auth:editors', $config);
        file_put_contents($this->project.'/config/panneau.php', $config);

        $this->panneau(['--upgrade' => true, '--no-js' => true]);

        $converted = require $this->project.'/config/panneau.php';
        $this->assertSame(['web', 'panneau.auth', 'App\\Http\\Middleware\\SetLocale'], $converted['middleware']);
        $this->assertSame('editors', $converted['auth']['guard']);
    }

    public function test_the_upgrade_replaces_a_provider_registered_by_its_short_name(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/config/app.php', "<?php\n\nreturn ['providers' => []];\n");
        mkdir($this->project.'/bootstrap');
        file_put_contents($this->project.'/bootstrap/providers.php', <<<'PHP'
            <?php

            use App\Panneau\PanneauServiceProvider;

            return [
                App\Providers\AppServiceProvider::class,
                PanneauServiceProvider::class,
            ];
            PHP);
        $this->assertSame('bootstrap/providers.php:7', $this->finding('providers', 'PanneauServiceProvider registered in bootstrap/providers.php')->location);

        $this->panneau(['--upgrade' => true, '--no-js' => true]);

        $providers = $this->read('bootstrap/providers.php');
        $this->assertStringContainsString("    \\App\\Providers\\PanneauServiceProvider::class,\n];", $providers);
        $this->assertStringNotContainsString('    PanneauServiceProvider::class', $providers);
    }

    public function test_the_check_reads_the_js_imports_and_more_route_helpers(): void
    {
        $this->project('v1.4');

        $this->assertTrue($this->finding('packages', 'resources/js/components/Panneau.jsx imports @panneau/core, @panneau/field-localized, @panneau/app')->isManual());
        $this->assertTrue($this->finding('route-names', "route('panneau.home')")->isManual());
        $this->assertNull($this->audit()->section('route-names')->find("to_route('home')"));
    }

    public function test_the_upgrade_keeps_files_it_does_not_recognize(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/resources/js/panneau.tsx', "// The project's entry\n");

        $output = $this->panneau(['--upgrade' => true]);

        $this->assertSame("// The project's entry\n", $this->read('resources/js/panneau.tsx'));
        $this->assertStringContainsString('KEPT (not a 1.x file', $output);
        $this->assertFileDoesNotExist($this->project.'/resources/js/panneau.tsx.1x.bak');
    }

    public function test_the_upgrade_converts_a_1_3_project(): void
    {
        $this->project('v1.3');
        $provider1x = $this->read('app/Providers/PanneauServiceProvider.php');
        $providers = $this->read('bootstrap/providers.php');

        $this->panneau(['--upgrade' => true, '--no-js' => true]);

        $config = require $this->project.'/config/panneau.php';
        $this->assertSame('admin', $config['path']);
        $this->assertSame('admin', $config['auth']['guard']);
        $this->assertSame(['web', 'panneau.auth', 'App\\Http\\Middleware\\SetLocale'], $config['middleware']);
        $this->assertNull($config['name']);

        // The 1.x provider was where the 5.x one goes: it is replaced, and stays registered
        $this->assertStringContainsString('Panneau::resources([', $this->read('app/Providers/PanneauServiceProvider.php'));
        $this->assertSame($provider1x, $this->read('app/Providers/PanneauServiceProvider.php.1x.bak'));
        $this->assertSame($providers, $this->read('bootstrap/providers.php'));

        $this->assertFileDoesNotExist($this->project.'/resources/js/panneau.tsx');
        $this->assertFileDoesNotExist($this->project.'/vite.config.mjs');
        $this->assertPhpFilesAreValid();
    }

    public function test_the_upgrade_writes_the_entry_in_the_js_directory_of_the_project(): void
    {
        $this->project('v1.3');
        $this->assertSame('resources/assets/js/panneau.tsx', $this->audit()->entry());
        $this->assertStringStartsWith('--upgrade writes the entry there', $this->finding('packages', "the project's JS is in resources/assets/js")->target);

        $this->panneau(['--upgrade' => true]);

        $this->assertStringContainsString('createPanneauApp', $this->read('resources/assets/js/panneau.tsx'));
        $this->assertDirectoryDoesNotExist($this->project.'/resources/js');
        $this->assertStringContainsString("input: ['resources/assets/js/panneau.tsx']", $this->read('vite.config.mjs'));
        $this->assertSame('resources/assets/js/panneau.tsx', (require $this->project.'/config/panneau.php')['app']['entry']);
        // The entry isn't one of the files to move with the codemods; a second run keeps it
        $this->assertNull($this->audit()->section('packages')->find('resources/assets/js/panneau.tsx imports @panneau/app'));
        $after = $this->snapshot();
        $this->panneau(['--upgrade' => true]);
        $this->assertSame($after, $this->snapshot());
    }

    public function test_the_upgrade_writes_the_entry_in_resources_js_by_default(): void
    {
        $this->project('v1.4');

        $this->assertSame('resources/js/panneau.tsx', $this->audit()->entry());
        $this->assertNull($this->audit()->section('packages')->find("the project's JS is in resources/js"));
    }

    public function test_it_tells_when_there_is_nothing_to_upgrade(): void
    {
        $this->project('');

        $this->assertStringContainsString('Nothing of Panneau 1.x is left', $this->panneau(['--upgrade' => true, '--check' => true]));
        $this->assertStringContainsString('No 1.x Panneau found', $this->panneau(['--upgrade' => true], 1));
        $this->assertFileDoesNotExist($this->project.'/config/panneau.php');
    }

    private function assertPhpFilesAreValid(): void
    {
        foreach ((new Filesystem)->allFiles($this->project) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $status);
            $this->assertSame(0, $status, implode("\n", $output));
        }
    }
}
