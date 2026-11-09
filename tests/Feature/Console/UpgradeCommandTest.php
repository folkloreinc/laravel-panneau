<?php

namespace Panneau\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Panneau\Auth\ResetUserPassword;
use Panneau\Auth\UpdateUserPassword;
use Panneau\Auth\UpdateUserProfileInformation;
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
        $this->assertSame('types(), whose ResourceTypes the Rector rules convert', $pages->detail('$types: 2 types')?->target);
        $this->assertSame('manual: block types become BlockTypes of a Repeater', $this->finding('resources', 'Blocks (blocks)')->detail('$types: 1 type')?->target);
        $this->assertSame('nothing: the default', $pages->detail("\$settings['hideInNavbar'] = false")?->target);

        $index = $pages->detail('index()');
        $this->assertNotNull($index);
        $this->assertSame('$list->columns([...]) of the fields', $index->detail('columns: title, type, sites, published')?->target);
        $this->assertSame('$list->sorts([...])', $index->detail('sortable: title')?->target);
        $this->assertSame('RelationFilter on a resource', $index->detail('filter site_id: select')?->target);
        // The duplicate is counted once, on the button of the column
        $this->assertSame('nothing: the duplicate button of the actions column', $index->detail("actionsProps: ['hasDuplicateRoute' => true]")?->target);
        // The buttons of each row become actions of the resource
        $this->assertSame(
            // The four of the 5.x default, once the resource says what a copy includes
            'manual: duplicate, with duplication() on the resource and what a copy includes; nothing: the built-in item actions of the default, by the abilities of the policy',
            $index->detail('column actions: show, edit, duplicate, delete')?->target,
        );
        $this->assertTrue((bool) $index->detail('column actions: show, edit, duplicate, delete')?->isManual());
        $this->assertSame(
            // Without duplicate, until the resource opts in, they are the default
            'nothing: the built-in item actions of the default, by the abilities of the policy',
            $this->finding('resources', 'Projects (projects)')->detail('index()')?->detail('column actions: show, edit, delete')?->target,
        );
        $this->assertTrue((bool) $index->detail('column sites: component site-badges')?->isManual());

        // 1.x ids are camel case, 5.x ids kebab case
        $jobs = $this->finding('resources', 'JobListings (jobListings)');
        $this->assertStringStartsWith('job-listings in 5.x', (string) $jobs->detail('id jobListings')?->target);
        $this->assertNull($pages->detail('id pages'));
        $this->assertTrue((bool) $jobs->detail("\$settings['hideInNavbar'] = true")?->isManual());

        $this->assertStringStartsWith('manual: a BlockType', $this->finding('types', 'TextBlock (text) of Blocks')->target);
        $this->assertSame(
            'a subclass of Panneau\\Resources\\ResourceType: the Rector rules convert it',
            $this->finding('types', 'Page (page) of Pages')->target,
            'A type that other types extend is still a type when a resource lists it.',
        );
        $home = $this->finding('types', 'Home (home) of Pages');
        $this->assertFalse($home->isManual());
        $this->assertSame('canCreate() of the type', $home->detail("\$settings['canCreate'] = false")?->target);
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
        // 5.x has a Password field too: it isn't counted
        $this->assertNull($this->audit()->section('field-uses')->find('Password ×1 (password)'));

        $orders = $this->finding('resources', 'Orders (orders)');
        $this->assertTrue((bool) $orders->detail('settings()')?->isManual());
        $this->assertSame('actions(): LinkAction, or actions of the resource', $orders->detail('index()')?->detail('actions: 1 buttons')?->target);
        $this->assertSame("itemActions(): ['show', 'edit'], by the abilities of the policy", $orders->detail('index()')?->detail('column actions: show, edit')?->target);
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

    public function test_the_check_lists_the_resource_type_helpers_that_no_resource_uses(): void
    {
        $this->project('v1.3');
        file_put_contents($this->project.'/app/Panneau/Resources/BlockWithBlocks.php', <<<'PHP'
            <?php

            namespace App\Panneau\Resources;

            use Panneau\Contracts\ResourceType;

            abstract class BlockWithBlocks implements ResourceType
            {
            }
            PHP);

        // The types section leaves out abstract classes and helpers: the contracts section lists them
        foreach ($this->audit()->section('types')->findings as $finding) {
            $this->assertStringStartsNotWith('BlockWithBlocks', $finding->subject);
        }
        $this->assertSame('app/Panneau/Resources/BlockWithBlocks.php:7', $this->finding('contracts', 'BlockWithBlocks implements ResourceType')->location);
    }

    public function test_the_check_lists_the_uses_of_1x_contracts_once_by_file(): void
    {
        $this->project('v1.3');
        file_put_contents($this->project.'/app/Panneau/Http/Controllers/TagsController.php', <<<'PHP'
            <?php

            namespace App\Panneau\Http\Controllers;

            use Illuminate\Http\Request;
            use Panneau\Contracts\Resource;
            use Panneau\Http\Controllers\ResourceController;

            class TagsController extends ResourceController
            {
                protected function getIndexQueryFromRequest(Request $request, Resource $resource)
                {
                    return [];
                }
            }
            PHP);
        file_put_contents($this->project.'/app/Http/Resources/StoreResource.php', <<<'PHP'
            <?php

            namespace App\Http\Resources;

            use Panneau\Contracts\ResourceItem;

            class StoreResource
            {
                public function __construct(private ResourceItem $store) {}

                public function id(ResourceItem $item): string
                {
                    return $item->id();
                }
            }
            PHP);
        $contracts = $this->audit()->section('contracts');

        // Resource controllers are listed with their section
        $this->assertNotNull($this->audit()->section('controllers')->find('TagsController'));
        foreach ($contracts->findings as $finding) {
            $this->assertStringStartsNotWith('app/Panneau/Http/Controllers/', (string) $finding->location);
        }
        $this->assertSame('app/Http/Resources/StoreResource.php:9', $this->finding('contracts', 'Panneau\\Contracts\\ResourceItem ×2')->location);
    }

    public function test_the_check_leaves_the_files_to_delete_out_of_the_contracts(): void
    {
        $this->project('v1.4');

        // PanneauDefinition implements the 1.x Definition, and is listed with the files to delete
        $this->assertContains('app/Panneau/PanneauDefinition.php', $this->audit()->generatedFiles());
        $this->assertSame([], $this->audit()->section('contracts')->findings);
    }

    public function test_the_check_skips_the_branches_of_the_workflows(): void
    {
        $this->project('v1.3');

        // A branch named after Panneau, in the triggers, depends on nothing
        $this->assertNull($this->audit()->section('deployment')->find('- feature/13-panneau-5'));
        $this->assertNull($this->audit()->section('deployment')->find('branches: [develop, feature/13-panneau-5]'));
        $this->assertSame('.github/workflows/checks.yml:16', $this->finding('deployment', '- run: php artisan panneau:assets')->location);
    }

    public function test_the_check_skips_only_the_branches_of_the_triggers(): void
    {
        $this->project('v1.3');
        file_put_contents($this->project.'/.github/workflows/deploy-admin.yml', <<<'YAML'
            "on": { push: { branches: [panneau-5] } }

            jobs:
                deploy:
                    runs-on: ubuntu-24.04
                    steps:
                        - uses: example/deploy@v1
                          with:
                              branches: panneau-admin
            YAML);
        $deployment = $this->audit()->section('deployment');

        $this->assertNull($deployment->find('"on": { push: { branches: [panneau-5] } }'));
        // A branches: key of a step isn't a trigger
        $this->assertSame('.github/workflows/deploy-admin.yml:9', $this->finding('deployment', 'branches: panneau-admin')->location);
    }

    public function test_an_on_key_of_a_step_is_not_a_trigger(): void
    {
        $this->project('v1.3');
        file_put_contents($this->project.'/.github/workflows/deploy-admin.yml', <<<'YAML'
            on: workflow_dispatch

            jobs:
                deploy:
                    runs-on: ubuntu-24.04
                    steps:
                        - uses: example/deploy@v1
                          with:
                              on:
                                  branches: panneau-admin
            YAML);

        $this->assertSame('.github/workflows/deploy-admin.yml:10', $this->finding('deployment', 'branches: panneau-admin')->location);
    }

    public function test_the_check_keeps_the_hashing_of_1x_passwords_to_the_repository(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Panneau/Fields/Secret.php', "<?php\n\nnamespace App\\Panneau\\Fields;\n\nuse Panneau\\Fields\\Password;\n\nclass Secret extends Password\n{\n}\n");

        $secret = collect($this->audit()->section('fields')->findings)->first(fn (Finding $finding): bool => str_starts_with($finding->subject, 'Secret'));
        $this->assertNotNull($secret);
        $this->assertStringContainsString('hashed(false)', $secret->target);
    }

    public function test_the_check_points_groups_of_links_and_addresses_and_mailto_urls_to_their_types(): void
    {
        $this->project('v1.4');
        $fields = [
            'Button' => ['Fields', "TextLocalized::make('label'), UrlLocalized::make('url'), Toggle::make('external')"],
            'Cta' => ['Fields', "Text::make('text'), Url::make('link')"],
            'Venue' => ['Fields', "Text::make('name'), Text::make('address'), Text::make('city'), Text::make('postal_code'), Select::make('province')"],
            'Person' => ['Fields', "Text::make('name'), Url::make('url')"],
        ];
        foreach ($fields as $name => [$parent, $made]) {
            file_put_contents($this->project."/app/Panneau/Fields/{$name}.php", <<<PHP
                <?php

                namespace App\\Panneau\\Fields;

                use Panneau\\Fields\\{$parent};
                use Panneau\\Fields\\Select;
                use Panneau\\Fields\\Text;
                use Panneau\\Fields\\TextLocalized;
                use Panneau\\Fields\\Toggle;
                use Panneau\\Fields\\Url;
                use Panneau\\Fields\\UrlLocalized;

                class {$name} extends {$parent}
                {
                    public function fields(): array
                    {
                        return [{$made}];
                    }
                }
                PHP);
        }
        file_put_contents($this->project.'/app/Panneau/Fields/Contact.php', <<<'PHP'
            <?php

            namespace App\Panneau\Fields;

            use Illuminate\Http\Request;
            use Panneau\Fields\Url;

            class Contact extends Url
            {
                public function rules(Request $request): ?array
                {
                    return ['nullable', 'regex:/^(https?|mailto):/'];
                }
            }
            PHP);
        file_put_contents($this->project.'/app/Panneau/Fields/Website.php', <<<'PHP'
            <?php

            namespace App\Panneau\Fields;

            use Illuminate\Http\Request;
            use Panneau\Fields\Url;

            class Website extends Url
            {
                public function rules(Request $request): ?array
                {
                    return ['nullable', 'url', 'max:255'];
                }
            }
            PHP);

        $button = $this->finding('fields', 'Button extends Fields (fields)');
        $this->assertTrue($button->isManual());
        $this->assertStringStartsWith('manual: Link->localized()', $button->target);
        $this->assertStringContainsString('newTab', $button->target);
        $this->assertStringContainsString('(from label, url, external)', $button->target);
        $cta = $this->finding('fields', 'Cta extends Fields (fields)')->target;
        $this->assertStringStartsWith('manual: Link, a built-in type', $cta);
        $this->assertStringContainsString('the label a string per locale', $cta);
        $venue = $this->finding('fields', 'Venue extends Fields (fields)')->target;
        $this->assertStringStartsWith('manual: Address', $venue);
        $this->assertStringContainsString('panneau:convert-addresses', $venue);
        // Neither a link nor an address
        $this->assertSame('Group', $this->finding('fields', 'Person extends Fields (fields)')->target);
        $this->assertSame('Group', $this->finding('fields', 'Credit extends Fields (fields)')->target);
        $this->assertStringStartsWith("manual: Link->types(['url', 'email'])", $this->finding('fields', 'Contact extends Url (url)')->target);
        $this->assertSame('Url', $this->finding('fields', 'Website extends Url (url)')->target);
    }

    public function test_the_check_lists_passwords_that_dont_say_who_hashes(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Http/Fields.php', <<<'PHP'
            <?php

            namespace App\Http;

            use Panneau\Fields\Password;

            class Fields
            {
                public function all(): array
                {
                    return [
                        Password::make('password')->label('Password'),
                        Password::make('pin')->label('PIN')->hashed(false),
                        Password::make('code')->hashed(),
                        new Password('secret'),
                    ];
                }
            }
            PHP);

        // Outside the Rector rules' folder: 5.x would hash what the repository hashes again
        $password = $this->finding('field-uses', "Password::make('password') without hashed()");
        $this->assertSame('app/Http/Fields.php:12', $password->location);
        $this->assertStringStartsWith('->hashed(false) when the repository hashes', $password->target);
        // Saying who hashes settles it, either way
        $this->assertNull($this->audit()->section('field-uses')->find("Password::make('pin') without hashed()"));
        $this->assertNull($this->audit()->section('field-uses')->find("Password::make('code') without hashed()"));
        $this->assertSame('app/Http/Fields.php:15', $this->finding('field-uses', "new Password('secret') without hashed()")->location);
    }

    public function test_the_check_reads_first_class_callables(): void
    {
        // A first-class callable (`foo(...)`, PHP 8.1) has no arguments to read: getArgs() failed
        // on it, with an assertion or an undefined property (#184)
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Http/Callables.php', <<<'PHP'
            <?php

            namespace App\Http;

            use Illuminate\Support\Str;
            use Panneau\Fields\Number;
            use Panneau\Fields\Text;

            class Callables
            {
                public function all(): array
                {
                    $lower = array_map(Str::lower(...), ['A']);
                    $names = collect(['A'])->map($this->format(...));
                    $make = Text::make(...);

                    return [$make('name'), Number::make('price')->withAttributes(['append' => '$']), strlen(...)];
                }

                private function format(string $name): string
                {
                    return $name;
                }
            }
            PHP);

        $this->assertNotNull($this->audit()->section('field-uses'));
        $this->panneau(['--upgrade' => true, '--check' => true]);
    }

    public function test_the_check_suggests_date_ranges_and_money(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Http/EventFields.php', <<<'PHP'
            <?php

            namespace App\Http;

            use Panneau\Fields\Date;
            use Panneau\Fields\DateTime;
            use Panneau\Fields\Number;

            class EventFields
            {
                public function all(): array
                {
                    return [
                        DateTime::make('starts_at'),
                        DateTime::make('ends_at'),
                        Date::make('date_start'),
                        Date::make('date_end'),
                        Date::make('published_at'),
                        Number::make('price')->withAttributes(['append' => '$ CAD']),
                        Number::make('stock')->withAttributes(['append' => 'units']),
                    ];
                }
            }
            PHP);

        $range = $this->finding('field-uses', "DateTime::make('starts_at') and DateTime::make('ends_at')");
        $this->assertSame('app/Http/EventFields.php:14', $range->location);
        $this->assertStringStartsWith('DateRange', $range->target);
        $this->assertStringContainsString('AsDateRange', $range->target);
        $this->assertNotNull($this->audit()->section('field-uses')->find("Date::make('date_start') and Date::make('date_end')"));
        $this->assertCount(2, array_filter(
            $this->audit()->section('field-uses')->findings,
            fn (Finding $finding): bool => str_contains($finding->subject, ' and '),
        ));

        $money = $this->finding('field-uses', "Number::make('price') with a currency");
        $this->assertSame('app/Http/EventFields.php:19', $money->location);
        $this->assertStringContainsString('panneau:convert-money', $money->target);
        $this->assertNull($this->audit()->section('field-uses')->find("Number::make('stock') with a currency"));
    }

    public function test_the_check_lists_a_concrete_1x_base_of_types(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Panneau/Resources/Blocks/Block.php', str_replace('abstract class', 'class', $this->read('app/Panneau/Resources/Blocks/Block.php')));

        $finding = $this->finding('types', 'Block (base of 1 type)');
        $this->assertTrue($finding->isManual());
        $this->assertSame('app/Panneau/Resources/Blocks/Block.php:7', $finding->location);
        $this->assertNotNull($this->audit()->section('types')->find('TextBlock (text) of Blocks'));
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

    /**
     * Runs the Rector rules on the project's app/Panneau.
     */
    private function rector(): void
    {
        $root = dirname(__DIR__, 3);
        exec(sprintf(
            'cd %s && %s %s process app/Panneau --config %s --no-progress-bar --no-diffs 2>&1',
            escapeshellarg($this->project),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root.'/vendor/bin/rector'),
            escapeshellarg($root.'/rector.php'),
        ), $rector, $status);
        $this->assertSame(0, $status, implode("\n", $rector));
    }

    public function test_the_check_after_the_upgrade_and_the_rector_rules(): void
    {
        $this->project('v1.4');
        $this->panneau(['--upgrade' => true]);

        $this->rector();
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
        $this->assertSame([
            'mode' => 'external',
            'guard' => null,
            'login_url' => '/login',
            'logout_url' => null,
            'password_reset' => false,
            'passwords' => null,
            'reset_password_using' => ResetUserPassword::class,
            'email_verification' => false,
            'two_factor' => false,
            'two_factor_required' => false,
            'passkeys' => false,
            'providers' => [],
            'provider_users' => null,
            'update_profile_using' => UpdateUserProfileInformation::class,
            'update_password_using' => UpdateUserPassword::class,
            'password_timeout' => null,
            'rate_limit' => 6,
        ], $config['auth']);
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
        $this->assertSame('^5.0.0-alpha.10', $package['dependencies']['@panneau/app']);
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
        $this->assertStringContainsString("Set 'auth.mode' to 'panneau'", $output);
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

    /**
     * A regression test of Rector's file cache (#130). Where Rector keeps its cache in memory
     * (in CI, for some releases), the second run never sees the first one's cache, and this
     * test passes without the fix: it guards the regression where the file cache is used, as on
     * a developer's machine.
     */
    public function test_the_rector_rules_convert_again_what_another_run_left(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Panneau/Fields/Title.php', "<?php\n\nnamespace App\\Panneau\\Fields;\n\nuse App\\Support\\Field;\n\nclass Title extends Field\n{\n}\n");
        file_put_contents($this->project.'/app/Panneau/Notes.php', <<<'PHP'
            <?php

            namespace App\Panneau;

            use App\Panneau\Fields\Title;

            class Notes
            {
                public function fields(): array
                {
                    return [Title::make('title')->withLabel('Title')];
                }
            }
            PHP);
        $this->rector();
        $this->assertStringContainsString('->withLabel(', $this->read('app/Panneau/Notes.php'));

        // The field now extends a 1.x field: the rules convert its calls, in a file they left unchanged
        file_put_contents($this->project.'/app/Panneau/Fields/Title.php', str_replace('use App\\Support\\Field;', 'use Panneau\\Fields\\Text as Field;', $this->read('app/Panneau/Fields/Title.php')));
        $this->rector();

        $this->assertStringContainsString("Title::make('title')->label('Title')", $this->read('app/Panneau/Notes.php'));
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

    public function test_the_check_lists_only_what_is_left_of_1x_on_resources_moved_to_5x(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Panneau/Resources/Pages.php', <<<'PHP'
            <?php

            namespace App\Panneau\Resources;

            use Panneau\Resources\Resource;

            class Pages extends Resource
            {
                public function repository(): ?string
                {
                    return \App\Contracts\Repositories\Pages::class;
                }

                public function itemClass(): ?string
                {
                    return null;
                }

                public function pluralLabel(): string
                {
                    return 'Pages';
                }

                public function fields(): array
                {
                    return [];
                }

                public function toItem(object $item): array
                {
                    return [];
                }

                private function statusOptions(): array
                {
                    return [];
                }

                public function index(): array
                {
                    return [];
                }
            }
            PHP);
        file_put_contents($this->project.'/app/Panneau/Resources/Projects.php', <<<'PHP'
            <?php

            namespace App\Panneau\Resources;

            use Panneau\Mediatheque\MediathequeResource;

            class Projects extends MediathequeResource
            {
                public function mediaTypes(): array
                {
                    return ['image'];
                }
            }
            PHP);

        $pages = $this->finding('resources', 'Pages (pages)');
        $this->assertSame(['index()'], array_column($pages->details, 'subject'));
        $this->assertTrue($pages->detail('index()')->isManual());
        $projects = $this->finding('resources', 'Projects (projects)');
        $this->assertSame([], $projects->details);
        $this->assertSame(0, $projects->manualCount());
    }

    public function test_the_check_says_what_the_buttons_of_the_rows_become(): void
    {
        $this->project('v1.4');
        file_put_contents($this->project.'/app/Panneau/Resources/Projects.php', <<<'PHP'
            <?php

            namespace App\Panneau\Resources;

            use Panneau\Fields\Text;
            use Panneau\Support\Resource;

            class Projects extends Resource
            {
                public function index(): ?array
                {
                    return [
                        'columns' => [
                            ['id' => 'title', 'path' => 'title', 'actions' => ['create', 'edit', 'delete']],
                            ['id' => 'actions', 'actions' => ['print']],
                        ],
                    ];
                }

                public function fields(): array
                {
                    return [Text::make('title')];
                }
            }
            PHP);

        $index = $this->finding('resources', 'Projects (projects)')->detail('index()');
        $this->assertNotNull($index);
        // Without show, the resource may open its items in their form
        $this->assertSame(
            "itemActions(): ['edit', 'delete'], by the abilities of the policy; no show: open() can return 'edit'",
            $index->detail('column actions: create, edit, delete')?->target,
        );
        // The buttons on the column of a field leave the column
        $this->assertSame('$list->columns([...]) of the fields', $index->detail('columns: title')?->target);
        $this->assertTrue((bool) $index->detail('column actions: print')?->isManual());

        // Buttons that aren't a literal list are unknown
        file_put_contents($this->project.'/app/Panneau/Resources/Projects.php', <<<'PHP'
            <?php

            namespace App\Panneau\Resources;

            use Panneau\Fields\Text;
            use Panneau\Support\Resource;

            class Projects extends Resource
            {
                private const BUTTONS = ['edit'];

                public function index(): ?array
                {
                    return ['columns' => ['title', ['id' => 'actions', 'actions' => self::BUTTONS]]];
                }

                public function fields(): array
                {
                    return [Text::make('title')];
                }
            }
            PHP);
        $this->assertTrue((bool) $this->finding('resources', 'Projects (projects)')->detail('index()')?->detail('column actions: ?')?->isManual());

        // A duplicate route without a button is the one to opt in for
        file_put_contents($this->project.'/app/Panneau/Resources/Projects.php', <<<'PHP'
            <?php

            namespace App\Panneau\Resources;

            use Panneau\Fields\Text;
            use Panneau\Support\Resource;

            class Projects extends Resource
            {
                public function index(): ?array
                {
                    return ['columns' => ['title'], 'actionsProps' => ['hasDuplicateRoute' => true]];
                }

                public function fields(): array
                {
                    return [Text::make('title')];
                }
            }
            PHP);
        $this->assertSame(
            'manual: duplication() on the resource, with what a copy includes',
            $this->finding('resources', 'Projects (projects)')->detail('index()')?->detail("actionsProps: ['hasDuplicateRoute' => true]")?->target,
        );
    }

    public function test_the_check_leaves_out_the_js_of_panneau_5(): void
    {
        $this->project('v1.4');
        $plugin = "import { definePlugin } from '@panneau/core';\nimport TextField from '@panneau/fields/text';\n";
        file_put_contents($this->project.'/resources/js/plugin.ts', $plugin);
        $packages = fn (): array => array_column($this->audit()->section('packages')->findings, 'subject');

        // While package.json has @panneau/* 4, every file that imports @panneau/* is listed
        $this->assertContains('resources/js/plugin.ts imports @panneau/core, @panneau/fields', $packages());

        file_put_contents($this->project.'/package.json', json_encode(['dependencies' => ['@panneau/app' => '5.0.0-alpha.7', '@panneau/core' => '5.0.0-alpha.7', '@panneau/fields' => '5.0.0-alpha.7']]));
        $this->assertNotContains('resources/js/plugin.ts imports @panneau/core, @panneau/fields', $packages());
        // Modules of 4.x are still listed
        $this->assertContains('resources/js/components/Panneau.jsx imports @panneau/core, @panneau/field-localized', $packages());
        file_put_contents($this->project.'/resources/js/plugin.ts', $plugin."import { useResource } from '@panneau/core/contexts';\n");
        $this->assertContains('resources/js/plugin.ts imports @panneau/core', $packages());
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

    public function test_the_entry_of_an_earlier_upgrade_keeps_its_directory(): void
    {
        $this->project('v1.3');
        // Written by 5.0.0-alpha.6, which always used resources/js
        mkdir($this->project.'/resources/js');
        copy(dirname(__DIR__, 3).'/src/stubs/entry.stub', $this->project.'/resources/js/panneau.tsx');

        $this->assertSame('resources/js/panneau.tsx', $this->audit()->entry());
        $this->assertNull($this->audit()->section('packages')->find('resources/js/panneau.tsx imports @panneau/app'));
    }

    public function test_a_1x_file_named_like_the_entry_is_not_the_entry(): void
    {
        $this->project('v1.3');
        // A 1.x container at the root of resources/js: on macOS, Panneau.tsx is also panneau.tsx
        mkdir($this->project.'/resources/js');
        file_put_contents($this->project.'/resources/js/Panneau.tsx', "import { PanneauContainer } from '@panneau/app';\n");

        $this->assertSame('resources/assets/js/panneau.tsx', $this->audit()->entry());
        $this->assertNotNull($this->audit()->section('packages')->find('resources/js/Panneau.tsx imports @panneau/app'));
    }

    public function test_the_js_directory_is_the_one_package_json_or_tsconfig_names(): void
    {
        $this->project('v1.3');
        (new Filesystem)->deleteDirectory($this->project.'/resources/assets/js/components');
        mkdir($this->project.'/resources/js');
        // package.json names ./resources/assets/js/index.js
        $this->assertSame('resources/assets/js/panneau.tsx', $this->audit()->entry());

        // The name alone, without a trailing slash
        file_put_contents($this->project.'/package.json', '{"scripts": {"build": "flklr build"}}');
        file_put_contents($this->project.'/tsconfig.json', '{"include": ["resources/assets/js"]}');
        $this->assertSame('resources/assets/js/panneau.tsx', $this->audit()->entry());

        // Neither named nor imported, both there: resources/js
        unlink($this->project.'/tsconfig.json');
        $this->assertSame('resources/js/panneau.tsx', $this->audit()->entry());
    }

    public function test_the_js_directory_is_the_only_one_there_is(): void
    {
        $this->project('v1.3');
        (new Filesystem)->deleteDirectory($this->project.'/resources/assets/js/components');
        file_put_contents($this->project.'/package.json', '{"scripts": {"build": "flklr build"}}');

        $this->assertSame('resources/assets/js/panneau.tsx', $this->audit()->entry());
    }

    public function test_the_js_directory_with_the_most_panneau_imports_wins(): void
    {
        $this->project('v1.3');
        file_put_contents($this->project.'/package.json', '{"scripts": {"build": "flklr build ./resources/js/index.js"}}');
        mkdir($this->project.'/resources/js');
        file_put_contents($this->project.'/resources/js/admin.js', "import '@panneau/themes';\n");

        // One file each: package.json breaks the tie
        $this->assertSame('resources/js/panneau.tsx', $this->audit()->entry());

        file_put_contents($this->project.'/resources/assets/js/components/Fields.tsx', "import { Field } from '@panneau/fields';\n");
        $this->assertSame('resources/assets/js/panneau.tsx', $this->audit()->entry());
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
