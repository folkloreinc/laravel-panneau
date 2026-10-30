<?php

namespace Panneau\Upgrade;

use Illuminate\Support\Str;
use Panneau\Console\JsInstaller;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Reads a 1.3 or 1.4 project, without loading or running its code, and says what each part
 * becomes in 5.x: `panneau:install --upgrade --check`. `panneau:install --upgrade` writes what
 * it can from the same facts.
 *
 * @internal
 */
final class Audit
{
    public const CONFIG = 'config/panneau.php';

    public const ROUTES = 'routes/panneau.php';

    public const VIEWS = 'resources/views/vendor/panneau';

    private const ROUTE_VERBS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'any', 'match'];

    private const ROUTE_FACADES = ['Route', 'Illuminate\\Support\\Facades\\Route'];

    private const SERVICE_PROVIDERS = [
        'Illuminate\\Support\\ServiceProvider',
        'Illuminate\\Foundation\\Support\\Providers\\AuthServiceProvider',
        'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider',
        'Illuminate\\Foundation\\Support\\Providers\\RouteServiceProvider',
    ];

    private const PANNEAU_FACADES = ['Panneau', 'Panneau\\Support\\Facade'];

    /**
     * @var array<string, Section>
     */
    private array $sections = [];

    private ?string $configShape = null;

    private ?Expr\Array_ $config = null;

    /**
     * @var list<string>
     */
    private array $resources = [];

    private ?string $routesShape = null;

    private ?string $guard = null;

    private bool $usesPanneauLogin = false;

    /**
     * @var list<string>
     */
    private array $middleware = [];

    /**
     * @var list<PhpClass>
     */
    private array $providers = [];

    /**
     * @var list<string>
     */
    private array $generatedFiles = [];

    /**
     * @var list<string>|null
     */
    private ?array $sourceFiles = null;

    public function __construct(private readonly Codebase $code)
    {
        foreach ([
            'packages' => 'Packages',
            'config' => 'Config ('.self::CONFIG.')',
            'resources' => 'Resources',
            'types' => 'Resource types',
            'fields' => 'Field classes of the project',
            'field-uses' => 'Fields of 1.x and laravel-folklore 1.1 used by the project',
            'controllers' => 'Resource controllers',
            'routes' => 'Routes ('.self::ROUTES.')',
            'route-names' => 'Uses of 1.x route names',
            'providers' => 'Service providers and static resolvers',
            'generated' => 'Files the package now provides (to delete)',
            'macros' => 'Uses of the 1.x request macros and helpers',
            'deployment' => 'Build and deployment',
            'todos' => 'Left by the Rector rules (// TODO(panneau-5))',
        ] as $key => $title) {
            $this->sections[$key] = new Section($key, $title);
        }
        $this->auditPackages();
        $this->auditConfig();
        $this->readRegisteredResources();
        $this->auditResources();
        $this->auditResourceTypes();
        $this->auditFields();
        $this->auditControllers();
        $this->auditRoutes();
        $this->auditRouteNames();
        $this->auditProviders();
        $this->auditGeneratedFiles();
        $this->auditRequestMacros();
        $this->auditDeployment();
        $this->auditTodos();
        if ($this->configShape === '1.x') {
            $this->sections['config']->add('auth', 'mode external, on '.($this->guard === null ? 'the default guard' : "the guard {$this->guard}"), self::CONFIG);
        }
    }

    /**
     * @return list<Section>
     */
    public function sections(): array
    {
        return array_values(array_filter($this->sections, fn (Section $section): bool => $section->findings !== []));
    }

    public function section(string $key): Section
    {
        return $this->sections[$key];
    }

    /**
     * `1.x`, `5.x`, or null without a config.
     */
    public function configShape(): ?string
    {
        return $this->configShape;
    }

    public function config(): ?Expr\Array_
    {
        return $this->config;
    }

    /**
     * The resources of the 1.x config, in their order.
     *
     * @return list<string>
     */
    public function resources(): array
    {
        return $this->resources;
    }

    /**
     * `1.x` (Panneau::router()), `5.x` (Panneau::routes()), `other`, or null without a file.
     */
    public function routesShape(): ?string
    {
        return $this->routesShape;
    }

    /**
     * The guard of the 1.x routes' auth middleware: null for the default guard.
     */
    public function guard(): ?string
    {
        return $this->guard;
    }

    /**
     * Whether the 1.x routes served Panneau's own login (`Panneau::router()->auth()`).
     */
    public function usesPanneauLogin(): bool
    {
        return $this->usesPanneauLogin;
    }

    /**
     * Middleware of the 1.x routes other than the session, auth and the gate: they may belong in
     * `panneau.middleware`.
     *
     * @return list<string>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * The 1.x service providers of Panneau in the project.
     *
     * @return list<PhpClass>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * Files of the project that the package now provides, relative to the base path.
     *
     * @return list<string>
     */
    public function generatedFiles(): array
    {
        return $this->generatedFiles;
    }

    public function manualCount(): int
    {
        return array_sum(array_map(fn (Section $section): int => $section->manualCount(), $this->sections));
    }

    // Packages

    private function auditPackages(): void
    {
        $section = $this->sections['packages'];
        $composer = json_decode($this->code->read('composer.json') ?? '', true);
        $require = is_array($composer) ? (array) ($composer['require'] ?? []) : [];

        $panneau = $require['folklore/laravel-panneau'] ?? null;
        if (is_string($panneau) && ! $this->isMajor($panneau, 5)) {
            $section->add('folklore/laravel-panneau '.$panneau, 'folklore/laravel-panneau ^5.0@alpha', 'composer.json');
        }
        $folklore = $require['folklore/laravel-folklore'] ?? null;
        if (is_string($folklore) && ! $this->isMajor($folklore, 2)) {
            $section->add('folklore/laravel-folklore '.$folklore, 'folklore/laravel-folklore ^2.0@alpha: its Panneau adapter maps resources to Folklore repositories', 'composer.json');
        }

        foreach ($this->code->phpFiles(['resources/js', 'resources/assets/js'], '*.{js,jsx,ts,tsx,mjs}') as $file) {
            if ($file === JsInstaller::ENTRY) {
                continue;
            }
            preg_match_all('/(?:from\s+|import\s*\(\s*|require\s*\(\s*|import\s+)[\'"](@panneau\/[^\'"\/]+)/', $this->code->read($file) ?? '', $matches);
            if ($matches[1] !== []) {
                $section->add(
                    $file.' imports '.implode(', ', array_unique($matches[1])),
                    'manual: the JS codemods of @panneau/* 5; until then the build of the site breaks once @panneau/app is 5',
                    $file,
                );
            }
        }

        $package = json_decode($this->code->read('package.json') ?? '', true);
        if (! is_array($package)) {
            return;
        }
        foreach (['dependencies', 'devDependencies'] as $dependencies) {
            foreach ((array) ($package[$dependencies] ?? []) as $name => $version) {
                if (! is_string($name) || ! str_starts_with($name, '@panneau/') || ! is_string($version) || $this->isMajor($version, 5)) {
                    continue;
                }
                $section->add($name.' '.$version, $name === '@panneau/app'
                    ? '@panneau/app ^5.0.0-alpha.0 (--upgrade replaces it)'
                    : 'removed: 5.x has about a dozen packages, which @panneau/app brings (the JS codemods move the imports)', 'package.json');
            }
        }
    }

    private function isMajor(string $constraint, int $major): bool
    {
        return preg_match('/^[\^~>=v\s]*'.$major.'(\.|$|\s|@)/', $constraint) === 1;
    }

    // Config

    private function auditConfig(): void
    {
        $section = $this->sections['config'];
        $return = $this->code->find(self::CONFIG, Stmt\Return_::class)[0] ?? null;
        if (! $return?->expr instanceof Expr\Array_) {
            if ($this->code->exists(self::CONFIG)) {
                $section->add(self::CONFIG, 'manual: it doesn\'t return an array the upgrade can read', self::CONFIG);
            }

            return;
        }
        $this->config = $return->expr;
        $items = Php::items($this->config);
        $is1x = array_intersect(array_keys($items), V1::CONFIG_KEYS) !== [];
        $this->configShape = $is1x ? '1.x' : '5.x';
        foreach (Php::items(Php::get($this->config, 'resources')) as $resource) {
            $class = Php::className($resource);
            if ($class !== null) {
                $this->resources[] = $class;
            }
        }
        if (! $is1x) {
            return;
        }

        $location = fn (?Expr $expr): string => self::CONFIG.':'.($expr?->getStartLine() ?? $this->config->getStartLine());
        foreach ($items as $key => $value) {
            match ($key) {
                'name' => $section->add("name: {$this->describe($value)}", 'name', $location($value)),
                'resources' => $section->add(count($this->resources).' resources', 'Panneau::resources([...]) in the service provider', $location($value)),
                'only_declared_fields' => $section->add('only_declared_fields', 'nothing: 5.x only saves declared fields', $location($value)),
                'intl' => $this->auditIntlConfig($section, $value, $location),
                'routes' => $this->auditRoutesConfig($section, $value, $location),
                default => $section->add("{$key}: {$this->describe($value)}", "kept: it isn't a 1.x key", $location($value)),
            };
        }
    }

    /**
     * @param  callable(?Expr): string  $location
     */
    private function auditIntlConfig(Section $section, Expr $intl, callable $location): void
    {
        foreach (Php::items($intl) as $key => $value) {
            $section->add("intl.{$key}: {$this->describe($value)}", match ($key) {
                'locales' => 'locales',
                'locale' => "nothing: the app's locale",
                'translations' => 'nothing: labels are set on fields and resources, with __()',
                default => 'manual: not a 1.x key',
            }, $location($value));
        }
    }

    /**
     * @param  callable(?Expr): string  $location
     */
    private function auditRoutesConfig(Section $section, Expr $routes, callable $location): void
    {
        foreach (Php::items($routes) as $key => $value) {
            if ($key === 'middleware') {
                foreach ($this->middlewareNames($value) as $middleware) {
                    $this->auditMiddleware($section, $middleware, $location($value));
                }

                continue;
            }
            $described = $this->describe($value);
            $section->add("routes.{$key}: {$described}", match ($key) {
                'prefix' => 'path',
                'domain' => 'domain',
                'map' => str_contains($described, 'routes/panneau.php')
                    ? 'nothing: the service provider loads routes/panneau.php'
                    : 'manual: the service provider loads routes/panneau.php',
                'custom' => Php::value($value) === [] ? 'nothing' : 'manual: custom routes become actions or pages of a plugin',
                'name_prefix' => 'nothing: routes are named panneau.app and panneau.api.*',
                'without_patterns' => 'nothing',
                default => 'manual: not a 1.x key',
            }, $location($value));
        }
    }

    private function describe(?Expr $expr): string
    {
        return Php::describe($expr);
    }

    // Resources

    /**
     * The resources that the project's providers register with `Panneau::resources([...])`, as
     * the 5.x provider does once the config is converted.
     */
    private function readRegisteredResources(): void
    {
        foreach ($this->code->subclassesOf(self::SERVICE_PROVIDERS) as $provider) {
            foreach ($provider->find(Expr\StaticCall::class) as $call) {
                if (! $call->class instanceof Node\Name || ! in_array($call->class->toString(), self::PANNEAU_FACADES, true)
                    || $this->callName($call) !== 'resources') {
                    continue;
                }
                foreach (Php::items($call->getArgs()[0]->value ?? null) as $resource) {
                    $class = Php::className($resource);
                    if ($class !== null && ! in_array($class, $this->resources, true)) {
                        $this->resources[] = $class;
                    }
                }
            }
        }
    }

    private function auditResources(): void
    {
        $section = $this->sections['resources'];
        $classes = $this->resources;
        foreach ($this->code->subclassesOf([V1::RESOURCE]) as $class) {
            if (! $class->isAbstract() && ! in_array($class->name, $classes, true)) {
                $classes[] = $class->name;
            }
        }

        foreach ($classes as $name) {
            $class = $this->code->class($name);
            if ($class === null) {
                $section->add($name, 'manual: registered, but its class isn\'t in the project', self::CONFIG);

                continue;
            }
            $id1 = $this->resourceId($class);
            $id5 = Str::kebab(Str::endsWith($class->shortName(), 'Resource') ? Str::beforeLast($class->shortName(), 'Resource') : $class->shortName());
            $finding = $section->add(
                $class->shortName().' ('.$id1.')',
                in_array($name, $this->resources, true) ? 'a 5.x Resource' : 'a 5.x Resource (not registered: unused?)',
                $class->location(),
            );
            if ($id1 !== $id5) {
                $finding->add("id {$id1}", "{$id5} in 5.x: return '{$id1}' from id() to keep its URLs and the fields that name it");
            }
            foreach ($class->staticProperties() as $property) {
                $this->auditResourceProperty($finding, $class, $property);
            }
            foreach ($class->methods() as $method) {
                $this->auditResourceMethod($finding, $class, $method);
            }
        }
    }

    private function resourceId(PhpClass $class): string
    {
        $id = Php::value($class->returned('id'));

        return is_string($id) ? $id : Str::camel((string) preg_replace('/Resource$/', '', $class->shortName()));
    }

    private function auditResourceProperty(Finding $finding, PhpClass $class, string $property): void
    {
        $value = $class->staticProperty($property);
        $target = V1::RESOURCE_PROPERTIES[$property] ?? 'manual: not a 1.x property';
        if ($property === 'settings') {
            foreach (Php::items($value) as $key => $setting) {
                $default = V1::RESOURCE_DEFAULT_SETTINGS[$key] ?? Unknown::Value;
                $finding->add("\$settings['{$key}'] = {$this->describe($setting)}", Php::value($setting) === $default
                    ? 'nothing: the default'
                    : V1::RESOURCE_SETTINGS[$key] ?? 'manual: settings()');
            }

            return;
        }
        if ($property === 'types') {
            $count = count(Php::items($value));
            $finding->add("\$types: {$count} ".Str::plural('type', $count), $target);

            return;
        }
        if ($property === 'index') {
            $this->auditIndex($finding->add('$index', $target), $value);

            return;
        }
        $finding->add("\${$property} = {$this->describe($value)}", $target);
    }

    private function auditResourceMethod(Finding $finding, PhpClass $class, string $method): void
    {
        $target = match ($method) {
            'fields' => 'fields() (the Rector rules convert the fields)',
            'index' => 'list()',
            'name' => 'pluralLabel() and singularLabel()',
            'id' => 'id()',
            'translationsNamespace' => 'nothing: labels are set on fields and resources',
            'authorize' => 'manual: a policy for the item class',
            'types', 'settings', 'attributes', 'components', 'forms', 'makeRepository', 'makeJsonResource', 'makeJsonCollection', 'onlyDeclaredFields', 'indexIsPaginated' => 'manual: no 5.x equivalent',
            '__construct' => 'manual: resources are resolved from the container',
            default => 'manual: a method of the project',
        };
        $detail = $finding->add($method.'()', $target, $class->file.':'.$class->method($method)?->getStartLine());
        if ($method === 'index') {
            $this->auditIndex($detail, $class->returned('index'));
        }
    }

    /**
     * The columns, filters and actions of a 1.x `index()`.
     */
    private function auditIndex(Finding $finding, ?Expr $index): void
    {
        if (! $index instanceof Expr\Array_) {
            $finding->add('the index is built at runtime', 'manual: list()');

            return;
        }
        foreach (Php::items($index) as $key => $value) {
            match ($key) {
                'columns' => $this->auditColumns($finding, $value),
                'filters' => $this->auditFilters($finding, $value),
                'actions' => $finding->add('actions: '.count(Php::items($value)).' buttons', 'actions(): LinkAction, or actions of the resource'),
                'batchActions' => $finding->add('batchActions: '.count(Php::items($value)), 'actions() with ActionScope::Selection'),
                'actionsProps' => $finding->add('actionsProps: '.$this->describe($value), str_contains($this->describe($value), 'Duplicate')
                    ? 'DuplicateAction (not built yet)' : 'manual: actions()'),
                'withFadedId' => $finding->add('withFadedId', 'nothing'),
                default => $finding->add("{$key}: {$this->describe($value)}", 'manual: list() or a JS plugin'),
            };
        }
    }

    private function auditColumns(Finding $finding, Expr $columns): void
    {
        $ids = [];
        $sortable = [];
        foreach (Php::items($columns) as $column) {
            $value = Php::value($column);
            if (is_string($value)) {
                $ids[] = $value;

                continue;
            }
            $items = Php::items($column);
            $id = Php::value($items['id'] ?? $items['name'] ?? $items['path'] ?? null);
            $id = is_string($id) ? $id : '?';
            if ($id === 'actions' || isset($items['actions'])) {
                $actions = Php::value($items['actions'] ?? null);
                $names = is_array($actions) ? implode(', ', array_filter($actions, 'is_string')) : '?';
                $finding->add("column actions: {$names}", 'actions(): show and edit are built in, DeleteAction, DuplicateAction (not built yet)');

                continue;
            }
            $ids[] = $id;
            if (Php::value($items['sortable'] ?? null) === true) {
                $sortable[] = $id;
            }
            $component = Php::value($items['component'] ?? null);
            if (is_string($component) && ! in_array($component, ['text', 'text-localized', 'label', 'boolean', 'date', 'image', 'link', 'badges'], true)) {
                $finding->add("column {$id}: component {$component}", 'manual: the display of the field, or a JS plugin');
            }
        }
        $finding->add('columns: '.implode(', ', $ids), '$list->columns([...]) of the fields');
        if ($sortable !== []) {
            $finding->add('sortable: '.implode(', ', $sortable), '$list->sorts([...])');
        }
    }

    private function auditFilters(Finding $finding, Expr $filters): void
    {
        foreach (Php::items($filters) as $filter) {
            $items = Php::items($filter);
            $name = Php::value($items['name'] ?? $items['id'] ?? null);
            $component = Php::value($items['component'] ?? null);
            $name = is_string($name) ? $name : '?';
            $finding->add("filter {$name}: ".(is_string($component) ? $component : '?'), match (true) {
                $component === 'search' => '$list->search([...]): name the fields to search',
                isset($items['requestUrl']) => 'RelationFilter on a resource',
                $component === 'select', $component === 'radios' => 'SelectFilter, or ToggleFilter',
                default => 'manual: a filter of list()',
            });
        }
    }

    // Resource types

    private function auditResourceTypes(): void
    {
        $section = $this->sections['types'];
        $usedBy = [];
        // The 1.x $types, or types() once the Rector rules converted it
        foreach ($this->code->subclassesOf([V1::RESOURCE, 'Panneau\\Resources\\Resource']) as $resource) {
            foreach (Php::items($resource->staticProperty('types') ?? $resource->returned('types')) as $type) {
                $type = Php::className($type);
                if ($type !== null) {
                    $usedBy[$type][] = $resource->shortName();
                }
            }
        }
        $types = $this->code->subclassesOf([V1::RESOURCE_TYPE, V1::RESOURCE_TYPE_CONTRACT, 'Folklore\\Panneau\\Resources\\BlockWithBlocks']);
        $bases = array_filter(array_map(fn (PhpClass $type): ?string => $type->parent, $types));
        foreach ($types as $class) {
            // Bases of other types, and helpers that implement the contract, aren't types
            if ($class->isAbstract() || (! isset($usedBy[$class->name])
                && (in_array($class->name, $bases, true) || ! $this->code->isA($class->name, [V1::RESOURCE_TYPE])))) {
                continue;
            }
            $id = Php::value($class->returned('id'));
            $resources = $usedBy[$class->name] ?? [];
            $isBlock = str_contains($class->name, 'Block');
            $finding = $section->add(
                $class->shortName().(is_string($id) ? " ({$id})" : '').($resources !== [] ? ' of '.implode(', ', $resources) : ''),
                $isBlock ? 'manual: a BlockType of the Repeater of the blocks (BlockTypes::register() of laravel-folklore 2)' : 'manual: resource types are not built yet in 5.x',
                $class->location(),
            );
            $fields = $class->returned('fields');
            if ($fields instanceof Expr\Array_) {
                $finding->add(count($fields->items).' '.Str::plural('field', count($fields->items)), 'fields of the type');
            }
            foreach (Php::items($class->staticProperty('settings')) as $key => $setting) {
                $finding->add("\$settings['{$key}'] = {$this->describe($setting)}", $key === 'canCreate' ? 'the create ability of the policy' : 'manual: settings()');
            }
            foreach (array_diff($class->staticProperties(), ['settings']) as $property) {
                $finding->add("\${$property}", in_array($property, ['jsonResource', 'jsonCollection'], true)
                    ? 'toItem() of the resource' : 'manual: not a 1.x property');
            }
        }
    }

    // Fields

    private function auditFields(): void
    {
        $section = $this->sections['fields'];
        $known = array_keys(V1::FIELDS);
        $groups = [];
        foreach ($this->code->subclassesOf($known) as $class) {
            $base = $this->fieldBase($class->name);
            if ($base === null) {
                continue;
            }
            [$component, $target, $group] = V1::FIELDS[$base];
            $ownComponent = $this->fieldComponent($class->name);
            if ($base === V1::LOCALIZED_FIELD) {
                $target = $this->localizedTarget($class);
                if ($ownComponent !== null && $ownComponent !== 'localized' && ! str_starts_with($target, 'manual')) {
                    $target = "manual: {$target}; its component {$ownComponent} needs a JS plugin";
                }
            } elseif ($base === V1::FIELD) {
                $target = 'manual: a built-in type, or a field type with a JS plugin ('.($ownComponent ?? '?').')';
            } elseif ($ownComponent !== null && $ownComponent !== $component && ! in_array($ownComponent, ['fields', 'item', 'items'], true)) {
                $target = 'manual: '.preg_replace('/ \\(.*\\)$/', '', $target)."; its component {$ownComponent} needs a JS plugin";
            }
            $groups[$group][] = [$class, $base, $ownComponent ?? $component, $target];
        }

        $order = [V1::GROUP_ITEMS, V1::GROUP_FIELDS, V1::GROUP_RELATION, V1::GROUP_LOCALIZED, V1::GROUP_FIELD, V1::GROUP_BUILT_IN, V1::GROUP_FOLKLORE];
        foreach ($order as $group) {
            foreach ($groups[$group] ?? [] as [$class, $base, $component, $target]) {
                $parent = (string) $class->parent;
                $extends = class_basename($parent) === $class->shortName() ? $parent : class_basename($parent);
                if ($parent !== $base) {
                    $extends .= ' → '.(in_array(class_basename($base), [$class->shortName(), class_basename($parent)], true) ? $base : class_basename($base));
                }
                $section->add(
                    sprintf('%s extends %s (%s)', $class->shortName(), $extends, $component === '' ? '?' : $component),
                    $target,
                    $class->location(),
                );
            }
        }

        $uses = $this->sections['field-uses'];
        $counts = [];
        foreach ($this->sourceFiles() as $file) {
            foreach ([...$this->code->find($file, Expr\StaticCall::class), ...$this->code->find($file, Expr\New_::class)] as $node) {
                $class = $node->class instanceof Node\Name ? $node->class->toString() : null;
                // Classes that 5.x has under the same name (Text, Select…) are left out: once
                // converted, they would still be counted
                if ($class !== null && isset(V1::FIELDS[$class]) && ! in_array($class, [V1::FIELD, V1::LOCALIZED_FIELD], true)
                    && ! (str_starts_with($class, 'Panneau\\Fields\\') && class_exists($class))
                    && ($node instanceof Expr\New_ || ($node->name instanceof Node\Identifier && $node->name->toString() === 'make'))) {
                    $counts[$class] = ($counts[$class] ?? 0) + 1;
                }
            }
        }
        ksort($counts);
        foreach ($counts as $class => $count) {
            $uses->add(class_basename($class).' ×'.$count.' ('.V1::FIELDS[$class][0].')', V1::FIELDS[$class][1]);
        }
    }

    /**
     * The nearest 1.x or laravel-folklore 1.1 field class a class extends.
     */
    private function fieldBase(string $class): ?string
    {
        foreach ($this->code->ancestors($class) as $ancestor) {
            if (isset(V1::FIELDS[$ancestor])) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * The component a field class sends to the JS, when it or a parent in the project returns a
     * literal from component().
     */
    private function fieldComponent(string $name): ?string
    {
        foreach ([$name, ...$this->code->ancestors($name)] as $class) {
            $component = $this->code->class($class)?->returnedString('component');
            if ($component !== null) {
                return $component;
            }
        }

        return null;
    }

    private function localizedTarget(PhpClass $class): string
    {
        foreach ([$class->name, ...$this->code->ancestors($class->name)] as $name) {
            $method = $this->code->class($name)?->method('field');
            if ($method === null) {
                continue;
            }
            $finder = new NodeFinder;
            $node = $finder->findFirst($method->stmts ?? [], fn (Node $node): bool => ($node instanceof Expr\New_ && $node->class instanceof Node\Name)
                || ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier && $node->name->toString() === 'make'));
            $inner = $node instanceof Expr\New_ || $node instanceof Expr\StaticCall ? $node->class->toString() : null;
            if ($inner === null) {
                break;
            }
            $base = isset(V1::FIELDS[$inner]) ? $inner : $this->fieldBase($inner);
            if ($base === null || $base === V1::FIELD) {
                return 'manual: the field of field() ('.class_basename($inner).'), ->localized()';
            }
            $target = (string) preg_replace('/ \\(.*\\)$/', '', V1::FIELDS[$base][1]).'->localized()';
            $component = $this->fieldComponent($inner);
            if ($component !== null && $component !== V1::FIELDS[$base][0]) {
                return "manual: {$target}; the component {$component} of ".class_basename($inner).' needs a JS plugin';
            }

            return $target;
        }

        return 'manual: the field of field(), ->localized()';
    }

    // Controllers

    private function auditControllers(): void
    {
        $section = $this->sections['controllers'];
        $usedBy = [];
        foreach ($this->code->subclassesOf([V1::RESOURCE]) as $resource) {
            $controller = Php::className($resource->staticProperty('controller'));
            if ($controller !== null) {
                $usedBy[$controller][] = $resource->shortName();
            }
        }
        foreach ($this->code->subclassesOf([V1::RESOURCE_CONTROLLER]) as $class) {
            $resources = $usedBy[$class->name] ?? [];
            $finding = $section->add(
                $class->shortName().($resources !== [] ? ' of '.implode(', ', $resources) : ''),
                'hooks of the resource and actions, then delete it',
                $class->location(),
            );
            foreach ($class->methods() as $method) {
                $node = $class->method($method);
                $finding->add($method.'()', V1::CONTROLLER_METHODS[$method] ?? ($node?->isPublic()
                    ? 'manual: an action of the resource, or a page of a plugin'
                    : 'manual: a hook of the resource'), $class->file.':'.$node?->getStartLine());
            }
        }
    }

    // Routes

    private function auditRoutes(): void
    {
        $section = $this->sections['routes'];
        $stmts = $this->code->parse(self::ROUTES);
        if ($stmts === null) {
            return;
        }
        $calls = $this->code->find(self::ROUTES, Expr\CallLike::class);
        $names = array_map(fn (Expr\CallLike $call): ?string => $this->callName($call), $calls);
        $this->routesShape = match (true) {
            in_array('router', $names, true) => '1.x',
            in_array('routes', $names, true) => '5.x',
            default => 'other',
        };
        if ($this->routesShape !== '1.x') {
            return;
        }

        foreach ($calls as $call) {
            $name = $this->callName($call);
            if ($name === 'auth' && $call instanceof Expr\MethodCall && $this->callName($call->var) === 'router') {
                $this->usesPanneauLogin = true;
                $section->add('Panneau::router()->auth(): the login page of Panneau', "manual: 5.x has no login page yet: 'auth.login_url' must be a login page of the site", self::ROUTES.':'.$call->getStartLine());
            }
            if ($name === 'resources' && $call instanceof Expr\MethodCall && $this->callName($call->var) === 'router') {
                $section->add('Panneau::router()->resources()', 'Panneau::routes()', self::ROUTES.':'.$call->getStartLine());
            }
            if ($name === 'middleware') {
                foreach ($call->getArgs() as $arg) {
                    foreach ($this->middlewareNames($arg->value) as $middleware) {
                        $this->auditMiddleware($section, $middleware, self::ROUTES.':'.$call->getStartLine());
                    }
                }
            }
            if (in_array($name, self::ROUTE_VERBS, true) && $this->isRouteCall($call)) {
                $this->auditRoute($section, $call);
            }
        }
        if (str_contains($this->code->read(self::ROUTES) ?? '', 'name_prefix')) {
            $section->add('route names with panneau.routes.name_prefix', 'nothing: the 5.x routes are named panneau.app and panneau.api.*', self::ROUTES);
        }
    }

    private function auditMiddleware(Section $section, string $middleware, string $location): void
    {
        if ($section->find("middleware {$middleware}") !== null) {
            return;
        }
        if ($middleware === 'web') {
            $section->add("middleware {$middleware}", "'middleware' in config/panneau.php", $location);

            return;
        }
        if ($middleware === 'auth' || str_starts_with($middleware, 'auth:') || str_ends_with($middleware, '\\Authenticate')) {
            if (str_starts_with($middleware, 'auth:')) {
                $this->guard = explode(',', substr($middleware, 5))[0];
            }
            $section->add("middleware {$middleware}", 'panneau.auth, on '.(str_starts_with($middleware, 'auth:') ? 'the guard '.$this->guard : 'the default guard'), $location);

            return;
        }
        if (str_starts_with($middleware, 'can:') || str_starts_with($middleware, 'guest')) {
            $section->add("middleware {$middleware}", str_starts_with($middleware, 'can:') ? 'the viewPanneau gate' : 'nothing', $location);

            return;
        }
        if (str_ends_with($middleware, 'DispatchHandlingRequestEvent')) {
            $section->add("middleware {$middleware}", 'nothing: 5.x has no serving event', $location);

            return;
        }
        if (! in_array($middleware, $this->middleware, true)) {
            $this->middleware[] = $middleware;
        }
        $section->add("middleware {$middleware}", "'middleware' in config/panneau.php, if the admin still needs it", $location);
    }

    /**
     * @return list<string>
     */
    private function middlewareNames(Expr $expr): array
    {
        if ($expr instanceof Expr\Array_) {
            return array_merge(...array_map(fn (Expr $item): array => $this->middlewareNames($item), array_values(Php::items($expr))));
        }
        if ($expr instanceof Expr\BinaryOp\Concat) {
            $left = Php::value($expr->left);

            return is_string($left) ? [$left.Php::describe($expr->right)] : [];
        }
        $value = Php::className($expr) ?? Php::value($expr);

        return is_string($value) ? [$value] : [];
    }

    private function auditRoute(Section $section, Expr\CallLike $call): void
    {
        $name = (string) $this->callName($call);
        $args = array_map(fn (Arg $arg): Expr => $arg->value, $call->getArgs());
        if ($name === 'match') {
            array_shift($args);
        }
        $uri = Php::value($args[0] ?? null);
        $action = $args[1] ?? null;
        $handler = match (true) {
            $action instanceof Expr\Closure, $action instanceof Expr\ArrowFunction => 'a closure',
            $action instanceof Expr\Array_ => implode('@', array_map(fn (Expr $part): string => class_basename((string) (Php::className($part) ?? Php::value($part))), Php::items($action))),
            default => class_basename((string) (Php::className($action) ?? Php::value($action))),
        };
        $class = explode('@', $handler)[0];
        $target = isset(V1::GENERATED_CLASSES[$class])
            ? 'nothing: '.V1::GENERATED_CLASSES[$class]
            : 'manual: an action of a resource, or a page of a plugin';
        $section->add(strtoupper($name).' '.(is_string($uri) ? $uri : '?').' → '.$handler, $target, self::ROUTES.':'.$call->getStartLine());
    }

    private function isRouteCall(Expr $call): bool
    {
        while ($call instanceof Expr\MethodCall) {
            $call = $call->var;
        }

        return $call instanceof Expr\StaticCall && $call->class instanceof Node\Name
            && in_array($call->class->toString(), self::ROUTE_FACADES, true);
    }

    private function callName(Expr $call): ?string
    {
        $name = match (true) {
            $call instanceof Expr\MethodCall, $call instanceof Expr\StaticCall, $call instanceof Expr\NullsafeMethodCall => $call->name,
            $call instanceof Expr\FuncCall => $call->name,
            default => null,
        };

        return $name instanceof Node\Identifier || $name instanceof Node\Name ? $name->toString() : null;
    }

    private function auditRouteNames(): void
    {
        $section = $this->sections['route-names'];
        $prefix = Php::value(Php::get($this->config, 'routes.prefix'));
        $prefix = is_string($prefix) ? str_replace('/', '.', trim($prefix, '/')) : 'panneau';
        $target = 'manual: the 5.x routes are panneau.app and panneau.api.*';
        foreach ([...$this->sourceFiles(), ...$this->code->phpFiles(['routes', 'config'])] as $file) {
            // route(), to_route(), redirect()->route(), URL::route(), Redirect::route()
            foreach ($this->code->find($file, Expr\CallLike::class) as $call) {
                $function = $this->callName($call);
                if (! in_array($function, ['route', 'to_route'], true) || $call instanceof Expr\New_
                    || ($function === 'to_route' && ! $call instanceof Expr\FuncCall)) {
                    continue;
                }
                $name = $call->getArgs()[0]->value ?? null;
                $value = $name instanceof String_ ? $name->value : ($name instanceof Expr\BinaryOp\Concat ? Php::value($name->left) : null);
                if (is_string($value) && str_starts_with($value, $prefix.'.')) {
                    $section->add("{$function}('{$value}')", $target, $file.':'.$call->getStartLine());
                }
            }
        }
        foreach ($this->code->phpFiles(['resources/views'], '*.blade.php') as $file) {
            $this->grep($section, $file, '/(route|to_route)\(\s*[\'"]('.preg_quote($prefix, '/').'\.[^\'"]*)/', fn (array $match): string => "{$match[1]}('{$match[2]}')", $target);
        }
    }

    // Providers and resolvers

    private function auditProviders(): void
    {
        $section = $this->sections['providers'];
        foreach ($this->code->subclassesOf(self::SERVICE_PROVIDERS) as $class) {
            $findings = [];
            foreach ($class->find(Expr\StaticCall::class) as $call) {
                $name = $call->class instanceof Node\Name ? $call->class->toString() : null;
                $method = $this->callName($call);
                if ($name === null || $method === null) {
                    continue;
                }
                $key = $name.'::'.$method;
                $location = $class->file.':'.$call->getStartLine();
                if (isset(V1::RESOLVERS[$key])) {
                    $findings[] = [class_basename($name).'::'.$method.'()', V1::RESOLVERS[$key], $location];
                } elseif (in_array($name, self::PANNEAU_FACADES, true) && in_array($method, ['serving', 'router', 'resource'], true)) {
                    // resources() and routes() are 5.x's too: a 5.x provider calls them
                    $findings[] = ["Panneau::{$method}()", $method === 'serving' ? "nothing: 5.x has no serving event; Panneau's routes are named panneau.*" : 'manual: Panneau::resources() and Panneau::routes()', $location];
                } elseif ($method === 'policy' && in_array($name, ['Gate', 'Illuminate\\Support\\Facades\\Gate'], true)
                    && Php::className($call->getArgs()[0]->value ?? null) === 'Panneau\\Panneau') {
                    $findings[] = ['Gate::policy(Panneau::class, …)', "the viewPanneau gate (Gate::define('viewPanneau', …))", $location];
                } elseif ((str_starts_with($name, 'Panneau\\Support\\') || str_starts_with($name, 'Panneau\\Fields\\') || str_starts_with($name, 'Folklore\\Panneau\\'))
                    && str_starts_with($method, 'set')) {
                    $findings[] = [class_basename($name).'::'.$method.'()', 'manual: 5.x has no static configuration', $location];
                }
            }
            foreach ($class->find(Expr\MethodCall::class) as $call) {
                $first = $call->getArgs()[0]->value ?? null;
                if ($this->callName($call) === 'composer' && $first instanceof String_ && str_starts_with($first->value, 'panneau::')) {
                    $findings[] = ["view composer of {$first->value}", 'nothing: the package builds the boot config of the page', $class->file.':'.$call->getStartLine()];
                }
            }
            $policies = $class->staticProperty('policies') ?? $this->instanceProperty($class, 'policies');
            foreach (Php::items($policies) as $key => $policy) {
                if ($key === 'Panneau\\Panneau') {
                    $findings[] = ['$policies[Panneau::class]', "the viewPanneau gate (Gate::define('viewPanneau', …))", $class->location()];
                }
            }
            if ($findings === []) {
                continue;
            }
            $is1x = str_contains($class->shortName(), 'Panneau') && ! $this->callsPanneauResources($class);
            if ($is1x) {
                $this->providers[] = $class;
            }
            $finding = $section->add($class->shortName(), match (true) {
                ! $is1x => 'manual: remove its 1.x calls',
                $class->name === $this->code->appNamespace().'Providers\\PanneauServiceProvider' => 'replaced by the 5.x provider (--upgrade, with a backup)',
                default => 'App\\Providers\\PanneauServiceProvider (--upgrade writes it), then delete it',
            }, $class->location());
            foreach ($findings as [$subject, $target, $location]) {
                $finding->add($subject, $target, $location);
            }
        }
        foreach ($this->providers as $provider) {
            if ($provider->name === $this->code->appNamespace().'Providers\\PanneauServiceProvider') {
                continue;
            }
            foreach (['config/app.php', 'bootstrap/providers.php'] as $file) {
                $line = $this->lineOf($file, $provider->name.'::class')
                    ?? (preg_match('/^use\s+\\\\?'.preg_quote($provider->name, '/').'\s*;/m', $this->code->read($file) ?? '') === 1
                        ? $this->lineOf($file, ' '.$provider->shortName().'::class') : null);
                if ($line !== null) {
                    $section->add("{$provider->shortName()} registered in {$file}", 'replaced by App\\Providers\\PanneauServiceProvider (--upgrade)', $file.':'.$line);
                }
            }
        }
    }

    private function instanceProperty(PhpClass $class, string $name): ?Expr
    {
        foreach ($class->node->getProperties() as $property) {
            foreach ($property->props as $prop) {
                if ($prop->name->toString() === $name) {
                    return $prop->default;
                }
            }
        }

        return null;
    }

    private function callsPanneauResources(PhpClass $class): bool
    {
        foreach ($class->find(Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name && in_array($call->class->toString(), self::PANNEAU_FACADES, true)
                && in_array($this->callName($call), ['resources', 'routes'], true)) {
                return true;
            }
        }

        return false;
    }

    private function lineOf(string $file, string $needle): ?int
    {
        $code = $this->code->read($file);
        if ($code === null) {
            return null;
        }
        foreach (explode("\n", $code) as $index => $line) {
            if (str_contains(' '.ltrim($line, '\\ '), ltrim($needle, '\\'))) {
                return $index + 1;
            }
        }

        return null;
    }

    // Generated files

    private function auditGeneratedFiles(): void
    {
        $section = $this->sections['generated'];
        foreach ($this->code->classes() as $class) {
            $inPanneau = preg_match('#(^|/)panneau/#i', $class->file) === 1;
            $short = $class->shortName();
            $target = match (true) {
                isset(V1::GENERATED_CLASSES[$short]) && ($inPanneau || str_contains($short, 'Panneau')) => V1::GENERATED_CLASSES[$short],
                $short === 'Controller' && $inPanneau => 'nothing: the base of the generated controllers',
                $this->code->isA($class->name, [V1::DEFINITION, V1::DEFINITION_CONTRACT]) => V1::GENERATED_CLASSES['PanneauDefinition'],
                $this->code->isA($class->name, V1::COMPOSERS) => V1::GENERATED_CLASSES['AppComposer'],
                default => null,
            };
            if ($target !== null) {
                $this->generatedFiles[] = $class->file;
                $section->add($class->file, $target, $class->location());
            }
        }
        foreach ($this->providers as $provider) {
            if ($provider->name !== $this->code->appNamespace().'Providers\\PanneauServiceProvider') {
                $this->generatedFiles[] = $provider->file;
                $section->add($provider->file, 'app/Providers/PanneauServiceProvider.php', $provider->location());
            }
        }
        foreach ($this->code->phpFiles([self::VIEWS], '*.blade.php') as $file) {
            $section->add($file, basename($file) === 'app.blade.php'
                ? 'the page of the package: --upgrade moves it aside (it shadows panneau::app); move what it adds by hand'
                : 'nothing: the 5.x page has no layout', $file);
            if (basename($file) !== 'app.blade.php') {
                $this->generatedFiles[] = $file;
            }
        }
        foreach (['lang/vendor/panneau', 'resources/lang/vendor/panneau'] as $directory) {
            if ($this->code->exists($directory)) {
                $this->generatedFiles[] = $directory;
                $section->add($directory, 'nothing: the translations of the 1.x package', $directory);
            }
        }
        if ($this->routesShape === '1.x') {
            $section->add(self::ROUTES, 'Panneau::routes() (--upgrade replaces it, with a backup)', self::ROUTES);
        }
    }

    // Request macros and helpers

    private function auditRequestMacros(): void
    {
        $section = $this->sections['macros'];
        foreach ($this->sourceFiles() as $file) {
            foreach ([...$this->code->find($file, Expr\MethodCall::class), ...$this->code->find($file, Expr\NullsafeMethodCall::class)] as $call) {
                $name = $this->callName($call);
                if ($name !== null && isset(V1::REQUEST_MACROS[$name])) {
                    $section->add("{$name}()", 'manual: '.V1::REQUEST_MACROS[$name].' (5.x has no such macro)', $file.':'.$call->getStartLine());
                }
            }
            foreach ($this->code->find($file, Expr\FuncCall::class) as $call) {
                $name = $this->callName($call);
                if ($name !== null && isset(V1::HELPERS[$name])) {
                    $section->add("{$name}()", 'manual: '.V1::HELPERS[$name], $file.':'.$call->getStartLine());
                }
            }
        }
        foreach ($this->code->phpFiles(['resources/views'], '*.blade.php') as $file) {
            $this->grep($section, $file, '/\$isPanneau\b|isPanneau\(\)/', fn (): string => '$isPanneau', "manual: request()->routeIs('panneau.*'): 5.x shares no \$isPanneau");
        }
    }

    // Deployment and build

    private function auditDeployment(): void
    {
        $section = $this->sections['deployment'];
        $package = json_decode($this->code->read('package.json') ?? '', true);
        $hasVite = collect(['vite.config.js', 'vite.config.ts', 'vite.config.mjs', 'vite.config.mts'])
            ->contains(fn (string $file): bool => $this->code->exists($file));
        if (is_array($package)) {
            $scripts = (array) ($package['scripts'] ?? []);
            $build = $scripts['build'] ?? null;
            if (! $hasVite) {
                $bundler = match (true) {
                    str_contains((string) json_encode($scripts), 'flklr') => '@folklore/cli (webpack)',
                    str_contains((string) json_encode($scripts), 'mix') => 'Laravel Mix',
                    str_contains((string) json_encode($scripts), 'webpack') => 'webpack',
                    default => 'its bundler',
                };
                $section->add("package.json: the site builds with {$bundler}", 'Vite builds the admin next to it: --upgrade writes vite.config.mjs, and the build:panneau and dev:panneau scripts', 'package.json');
            }
            if (is_string($build) && ! str_contains($build, 'vite') && ! str_contains($build, 'build:panneau')) {
                $section->add("scripts.build: {$build}", '--upgrade adds && npm run build:panneau', 'package.json');
            }
            $engines = $package['engines']['node'] ?? null;
            if (is_string($engines) && $this->isOldNode($engines)) {
                $section->add("engines.node {$engines}", 'manual: Vite 8 needs Node 20.19+ or 22.12+', 'package.json');
            }
        }
        foreach (['.nvmrc', '.node-version'] as $file) {
            $version = trim($this->code->read($file) ?? '');
            if ($version !== '' && $this->isOldNode($version)) {
                $section->add("{$file}: {$version}", 'manual: Vite 8 needs Node 20.19+ or 22.12+', $file);
            }
        }

        $envoy = $this->code->read('Envoy.blade.php');
        if ($envoy !== null) {
            $deploysPublic = preg_match('#public/[A-Za-z0-9_-]+/?[\'"]#', $envoy) === 1;
            if ($deploysPublic && ! str_contains($envoy, 'public/build')) {
                $line = null;
                foreach (explode("\n", $envoy) as $index => $text) {
                    if (preg_match('#public/[A-Za-z0-9_-]+/?[\'"]#', $text) === 1) {
                        $line = $index + 1;
                        break;
                    }
                }
                $section->add('Envoy.blade.php copies built files from public/', "manual: add public/build/, Vite's build of the admin", 'Envoy.blade.php:'.$line);
            }
            $this->grep($section, 'Envoy.blade.php', '/^.*(panneau|assets:view).*$/mi', fn (array $match): string => trim($match[0]), 'manual: check it for 5.x');
        }
        foreach ($this->code->phpFiles(['.github/workflows'], '*.yml') as $file) {
            $this->grep($section, $file, '/node-version:\s*[\'"]?(\d+)(?:\.(\d+))?/', fn (array $match): string => 'node-version '.$match[1].(isset($match[2]) ? '.'.$match[2] : ''), 'manual: Vite 8 needs Node 20.19+ or 22.12+', fn (array $match): bool => $this->isOldNode($match[1].'.'.($match[2] ?? '0')));
            $this->grep($section, $file, '/^.*panneau.*$/mi', fn (array $match): string => trim($match[0]), 'manual: check it for 5.x');
            $workflow = $this->code->read($file) ?? '';
            if (preg_match('#public/static#', $workflow) === 1 && ! str_contains($workflow, 'public/build')) {
                $this->grep($section, $file, '#^.*public/static.*$#m', fn (array $match): string => trim($match[0]), "manual: add public/build/, Vite's build of the admin");
            }
        }
        $composer = json_decode($this->code->read('composer.json') ?? '', true);
        foreach ((array) (is_array($composer) ? ($composer['scripts'] ?? []) : []) as $name => $commands) {
            foreach ((array) $commands as $command) {
                if (is_string($command) && str_contains($command, 'panneau')) {
                    $section->add("composer {$name}: {$command}", 'manual: check it for 5.x', 'composer.json');
                }
            }
        }
        $gitignore = $this->code->read('.gitignore');
        if ($gitignore !== null && ! $hasVite && ! str_contains($gitignore, 'public/build')) {
            $section->add('.gitignore', 'manual: ignore /public/build and /public/hot', '.gitignore');
        }
    }

    /**
     * The `// TODO(panneau-5)` comments of the Rector rules, where they couldn't convert.
     */
    private function auditTodos(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $this->grep($this->sections['todos'], $file, '/\/\/\s*TODO\(panneau-5\):?\s*(.*)$/m', fn (array $match): string => trim($match[1]), 'manual: left by the Rector rules');
        }
    }

    /**
     * Whether a Node version is older than what Vite 8 needs: 20.19+ or 22.12+. A major alone
     * (`20`) means its latest release.
     */
    private function isOldNode(string $version): bool
    {
        if (preg_match('/(\d+)(?:\.(\d+))?/', $version, $matches) !== 1) {
            return false;
        }
        $major = (int) $matches[1];
        $minor = isset($matches[2]) ? (int) $matches[2] : PHP_INT_MAX;

        return $major < 20 || $major === 21 || ($major === 20 && $minor < 19) || ($major === 22 && $minor < 12);
    }

    /**
     * Adds a finding for each match of a pattern in a file.
     *
     * @param  callable(array<int, string>): string  $subject
     * @param  (callable(array<int, string>): bool)|null  $filter
     */
    private function grep(Section $section, string $file, string $pattern, callable $subject, string $target, ?callable $filter = null): void
    {
        $code = $this->code->read($file) ?? '';
        if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return;
        }
        foreach ($matches as $match) {
            $values = array_map(fn (array $group): string => $group[0], $match);
            if ($filter !== null && ! $filter($values)) {
                continue;
            }
            $line = substr_count(substr($code, 0, $match[0][1]), "\n") + 1;
            $section->add($subject($values), $target, $file.':'.$line);
        }
    }

    /**
     * The PHP files of the project's PSR-4 directories.
     *
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        return $this->sourceFiles ??= $this->code->phpFiles($this->code->sourceDirectories());
    }
}
