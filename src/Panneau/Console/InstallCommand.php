<?php

namespace Panneau\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Writes what belongs to the project: the config, a service provider with the resources and
 * the `viewPanneau` gate, the routes file, optionally a first resource and its policy, and the
 * JS entry of the app with its Vite input and npm packages.
 *
 * @internal
 */
final class InstallCommand extends Command
{
    protected $signature = 'panneau:install
        {--guard= : The guard of the admin users}
        {--login-url= : Where unauthenticated users are sent}
        {--resource= : An Eloquent model to create a first resource and its policy for}
        {--no-js : Skip the JS entry, the Vite config and the npm packages}
        {--force : Overwrite existing files}';

    protected $description = 'Install Panneau: config, service provider, routes, a first resource and the JS entry';

    /**
     * The JS entry, which mounts @panneau/app in the page of `panneau.app.view`.
     */
    private const JS_ENTRY = 'resources/js/panneau.tsx';

    /**
     * npm packages, by section of package.json. The peer dependencies of @panneau/app are listed
     * so the project controls their versions. Until 5.0.0, v5 is published on the "alpha" tag
     * while "latest" is v4, so the range is explicit.
     */
    private const NPM_PACKAGES = [
        'dependencies' => [
            '@panneau/app' => '^5.0.0-alpha.0',
            '@tanstack/react-query' => '^5.0.0',
            'react' => '^19.2.0',
            'react-dom' => '^19.2.0',
        ],
        'devDependencies' => [
            '@vitejs/plugin-react' => '^5.2.0',
        ],
    ];

    private const VITE_CONFIGS = ['vite.config.js', 'vite.config.ts', 'vite.config.mjs', 'vite.config.mts'];

    /**
     * Columns never turned into fields.
     */
    private const SKIPPED_COLUMNS = ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'email_verified_at'];

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $namespace = $this->laravel->getNamespace();
        $guard = $this->option('guard') ?? $this->ask('Guard of the admin users', $this->defaultGuard());
        $loginUrl = $this->option('login-url') ?? $this->ask('Where are unauthenticated users sent?', '/login');
        $model = $this->option('resource') ?? $this->ask('Create a first resource for a model? (class name, empty to skip)');

        $resources = [];
        if (is_string($model) && trim($model) !== '') {
            $modelClass = $this->qualifyModel(trim($model), $namespace);
            if (! is_subclass_of($modelClass, Model::class)) {
                $this->components->error(sprintf('%s is not an Eloquent model.', $modelClass));

                return self::FAILURE;
            }
            $resources[] = $this->writeResource($modelClass, $namespace);
            $this->writePolicy($modelClass, $namespace);
        }

        $this->writeConfig(is_string($guard) && $guard !== '' ? $guard : null, is_string($loginUrl) ? $loginUrl : '/login');
        $this->writeProvider($namespace, $resources);
        $this->write(base_path('routes/panneau.php'), $this->stub('routes'));
        if (! $this->option('no-js')) {
            $this->write(base_path(self::JS_ENTRY), $this->stub('entry'));
            $this->updatePackageJson();
            $this->updateViteConfig();
        }

        $provider = $namespace.'Providers\\PanneauServiceProvider';
        if (ServiceProvider::addProviderToBootstrapFile($provider, $this->laravel->getBootstrapProvidersPath())) {
            $this->components->info(sprintf('Added %s to bootstrap/providers.php.', $provider));
        }

        $path = '/'.trim((string) $this->laravel['config']->get('panneau.path', 'panneau'), '/');
        $this->newLine();
        $this->components->info('Panneau is installed. Next steps:');
        $this->components->bulletList(array_values(array_filter([
            'Decide who can use the admin in the viewPanneau gate of app/Providers/PanneauServiceProvider.php: nobody can until then.',
            'Add resources in app/Panneau/Resources, and a policy for each of them: resources without a policy are denied.',
            $this->option('no-js') ? null : 'Run npm install, then npm run dev, and open '.$path.'.',
            'The admin API is at '.rtrim($path, '/').'/api.',
        ])));

        return self::SUCCESS;
    }

    private function defaultGuard(): string
    {
        $guard = $this->laravel['config']->get('auth.defaults.guard');

        return is_string($guard) ? $guard : 'web';
    }

    private function writeConfig(?string $guard, string $loginUrl): void
    {
        $config = (string) $this->files->get(__DIR__.'/../../config/panneau.php');
        $config = str_replace("'guard' => null,", "'guard' => ".var_export($guard, true).',', $config);
        $config = str_replace("'login_url' => '/login',", "'login_url' => ".var_export($loginUrl, true).',', $config);

        $this->write(config_path('panneau.php'), $config);
    }

    /**
     * @param  list<string>  $resources
     */
    private function writeProvider(string $namespace, array $resources): void
    {
        $list = implode('', array_map(fn (string $resource): string => '            \\'.$resource."::class,\n", $resources));

        $this->write(app_path('Providers/PanneauServiceProvider.php'), $this->stub('provider', [
            'namespace' => $namespace,
            'resources' => $list,
        ]));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return string The resource class.
     */
    private function writeResource(string $modelClass, string $namespace): string
    {
        $basename = class_basename($modelClass);
        $class = Str::plural($basename).'Resource';
        [$fields, $imports, $text] = $this->guessFields($modelClass);
        $quoted = fn (array $names): string => implode(', ', array_map(fn (string $name): string => var_export($name, true), $names));
        // Short texts first, then the other simple fields
        $columns = array_slice(array_values(array_unique([...$text, ...array_keys(array_filter(
            $fields,
            fn (string $field): bool => ! str_starts_with($field, 'Textarea::'),
        ))])), 0, 3);

        $this->write(app_path('Panneau/Resources/'.$class.'.php'), $this->stub('resource', [
            'namespace' => $namespace,
            'class' => $class,
            'model' => $basename,
            'imports' => implode("\n", array_map(fn (string $import): string => 'use '.$import.';', [$modelClass, ...$imports])),
            'fields' => implode('', array_map(fn (string $field): string => '            '.$field.",\n", $fields)),
            'columns' => $quoted($columns),
            'search' => $quoted(array_slice($text, 0, 2)),
        ]));

        return $namespace.'Panneau\\Resources\\'.$class;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function writePolicy(string $modelClass, string $namespace): void
    {
        $basename = class_basename($modelClass);
        $path = app_path('Policies/'.$basename.'Policy.php');
        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->twoColumnDetail('app/Policies/'.$basename.'Policy.php', '<fg=yellow>KEPT</>');

            return;
        }

        $this->write($path, $this->stub('policy', [
            'namespace' => $namespace,
            'class' => $basename.'Policy',
            'model' => $basename,
            'modelClass' => $modelClass,
            'variable' => Str::camel($basename),
            'plural' => Str::plural(Str::snake($basename, ' ')),
        ]));
    }

    /**
     * Fields guessed from the model's table: field code keyed by column, the imports, and
     * the text columns.
     *
     * @param  class-string<Model>  $modelClass
     * @return array{array<string, string>, list<string>, list<string>}
     */
    private function guessFields(string $modelClass): array
    {
        $model = new $modelClass;
        try {
            $columns = $model->getConnection()->getSchemaBuilder()->getColumns($model->getTable());
        } catch (Throwable) {
            $columns = [];
        }

        $fields = [];
        $imports = [];
        $text = [];
        foreach ($columns as $column) {
            $name = (string) $column['name'];
            if ($name === $model->getKeyName() || in_array($name, self::SKIPPED_COLUMNS, true)
                || in_array($name, $model->getHidden(), true)
                || str_ends_with($name, '_id') || str_ends_with($name, '_token')) {
                continue;
            }
            $guess = $this->guessField((string) $column['type_name'], (string) $column['type']);
            if ($guess === null) {
                continue;
            }
            [$fieldClass, $length] = $guess;
            $code = class_basename($fieldClass).'::make('.var_export($name, true).')';
            if (! $column['nullable'] && $column['default'] === null && class_basename($fieldClass) !== 'Boolean') {
                $code .= '->required()';
            }
            if ($length !== null) {
                $code .= '->max('.$length.')';
            }
            $fields[$name] = $code;
            $imports[$fieldClass] = $fieldClass;
            if (class_basename($fieldClass) === 'Text') {
                $text[] = $name;
            }
        }

        if (count($fields) === 0) {
            $fields['name'] = "Text::make('name')->required()";
            $imports[] = 'Panneau\\Fields\\Text';
            $text[] = 'name';
        }
        $imports = array_values(array_unique($imports));
        sort($imports);

        return [$fields, $imports, $text];
    }

    /**
     * @return array{class-string, int|null}|null
     */
    private function guessField(string $typeName, string $type): ?array
    {
        $typeName = strtolower($typeName);
        $length = preg_match('/^(?:var)?char(?:acter varying)?\((\d+)\)/i', $type, $matches) === 1 ? (int) $matches[1] : null;

        return match (true) {
            in_array($typeName, ['varchar', 'char', 'character varying', 'string', 'nvarchar'], true) => ['Panneau\\Fields\\Text', $length],
            in_array($typeName, ['text', 'tinytext', 'mediumtext', 'longtext'], true) => ['Panneau\\Fields\\Textarea', null],
            in_array($typeName, ['boolean', 'bool'], true) || $type === 'tinyint(1)' => ['Panneau\\Fields\\Boolean', null],
            in_array($typeName, ['integer', 'int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int2', 'int4', 'int8', 'decimal', 'numeric', 'float', 'double', 'real'], true) => ['Panneau\\Fields\\Number', null],
            $typeName === 'date' => ['Panneau\\Fields\\Date', null],
            in_array($typeName, ['datetime', 'timestamp', 'timestamptz', 'datetime2'], true) => ['Panneau\\Fields\\DateTime', null],
            in_array($typeName, ['time', 'timetz'], true) => ['Panneau\\Fields\\Time', null],
            default => null,
        };
    }

    private function qualifyModel(string $model, string $namespace): string
    {
        $model = ltrim(str_replace('/', '\\', $model), '\\');
        if (class_exists($model)) {
            return $model;
        }

        return class_exists($namespace.'Models\\'.$model) ? $namespace.'Models\\'.$model : $namespace.$model;
    }

    /**
     * Adds the npm packages that package.json doesn't have. An older major of @panneau/app is
     * replaced; the project's versions of the other packages are kept.
     */
    private function updatePackageJson(): void
    {
        $path = base_path('package.json');
        $package = $this->files->exists($path) ? json_decode((string) $this->files->get($path)) : null;
        if (! $package instanceof stdClass) {
            $this->components->warn('No valid package.json: add '.implode(', ', $this->npmPackageList()).'.');

            return;
        }

        $added = [];
        foreach (self::NPM_PACKAGES as $section => $packages) {
            foreach ($packages as $name => $version) {
                [$currentSection, $current] = $this->npmPackage($package, $name) ?? [$section, null];
                if ($current !== null && ($name !== '@panneau/app' || $this->major($current) >= 5)) {
                    if ($this->major($current) < $this->major($version)) {
                        $this->components->warn(sprintf('package.json has %s %s: Panneau needs %s.', $name, $current, $version));
                    }

                    continue;
                }
                $dependencies = (array) ($package->{$currentSection} ?? []);
                $dependencies[$name] = $version;
                ksort($dependencies);
                $package->{$currentSection} = (object) $dependencies;
                $added[] = $name.'@'.$version;
            }
        }

        if (count($added) === 0) {
            $this->components->twoColumnDetail('package.json', '<fg=yellow>KEPT</>');

            return;
        }
        $json = (string) json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $indent = preg_match('/^([ \t]+)"/m', (string) $this->files->get($path), $matches) === 1 ? $matches[1] : '    ';
        $json = (string) preg_replace_callback('/^(?: {4})+/m', fn (array $spaces): string => str_repeat($indent, intdiv(strlen($spaces[0]), 4)), $json);
        $this->files->put($path, $json."\n");
        $this->components->twoColumnDetail('package.json', '<fg=green>ADDED '.implode(', ', $added).'</>');
    }

    /**
     * The section and the version of an npm package in package.json.
     *
     * @return array{string, string}|null
     */
    private function npmPackage(stdClass $package, string $name): ?array
    {
        foreach (['dependencies', 'devDependencies'] as $section) {
            $version = ((array) ($package->{$section} ?? []))[$name] ?? null;
            if (is_string($version)) {
                return [$section, $version];
            }
        }

        return null;
    }

    /**
     * The first major version of an npm range, or PHP_INT_MAX when it has none (a tag, a URL).
     */
    private function major(string $range): int
    {
        return preg_match('/^[\^~>=<v\s]*(\d+)/', $range, $matches) === 1 ? (int) $matches[1] : PHP_INT_MAX;
    }

    /**
     * @return list<string>
     */
    private function npmPackageList(): array
    {
        $list = [];
        foreach (self::NPM_PACKAGES as $packages) {
            foreach ($packages as $name => $version) {
                $list[] = $name.'@'.$version;
            }
        }

        return $list;
    }

    /**
     * Adds the JS entry to the inputs of laravel-vite-plugin, and the React plugin.
     */
    private function updateViteConfig(): void
    {
        $file = collect(self::VITE_CONFIGS)->first(fn (string $file): bool => $this->files->exists(base_path($file)));
        $manual = sprintf("add '%s' to the input of laravel() and the react() plugin of @vitejs/plugin-react.", self::JS_ENTRY);
        if (! is_string($file)) {
            $this->components->warn('No Vite config: '.$manual);

            return;
        }

        $config = new ViteConfig((string) $this->files->get(base_path($file)));
        $input = $config->addInput(self::JS_ENTRY);
        $plugin = $config->addPlugin('@vitejs/plugin-react', 'react', 'react()');
        if ($input === null || $plugin === null) {
            $this->components->warn(sprintf('%s could not be updated: %s', $file, $manual));

            return;
        }
        if ($input === false && $plugin === false) {
            $this->components->twoColumnDetail($file, '<fg=yellow>KEPT</>');

            return;
        }
        $this->files->put(base_path($file), $config->source());
        $this->components->twoColumnDetail($file, '<fg=green>UPDATED</>');
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function stub(string $name, array $replacements = []): string
    {
        $stub = (string) $this->files->get(__DIR__.'/../../stubs/'.$name.'.stub');
        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ '.$key.' }}', $value, $stub);
        }

        return $stub;
    }

    private function write(string $path, string $contents): void
    {
        $relative = ltrim(Str::after($path, base_path()), '/');
        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->twoColumnDetail($relative, '<fg=yellow>KEPT (use --force to overwrite)</>');

            return;
        }
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
        $this->components->twoColumnDetail($relative, '<fg=green>WRITTEN</>');
    }
}
