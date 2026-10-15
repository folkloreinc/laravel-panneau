<?php

namespace Panneau\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes what belongs to the project: the config, a service provider with the resources and
 * the `viewPanneau` gate, the routes file, and optionally a first resource and its policy.
 *
 * @internal
 */
final class InstallCommand extends Command
{
    protected $signature = 'panneau:install
        {--guard= : The guard of the admin users}
        {--login-url= : Where unauthenticated users are sent}
        {--resource= : An Eloquent model to create a first resource and its policy for}
        {--force : Overwrite existing files}';

    protected $description = 'Install Panneau: config, service provider, routes and a first resource';

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

        $provider = $namespace.'Providers\\PanneauServiceProvider';
        if (ServiceProvider::addProviderToBootstrapFile($provider, $this->laravel->getBootstrapProvidersPath())) {
            $this->components->info(sprintf('Added %s to bootstrap/providers.php.', $provider));
        }

        $this->newLine();
        $this->components->info('Panneau is installed. Next steps:');
        $this->components->bulletList([
            'Decide who can use the admin in the viewPanneau gate of app/Providers/PanneauServiceProvider.php: nobody can until then.',
            'Add resources in app/Panneau/Resources, and a policy for each of them: resources without a policy are denied.',
            'The admin API is at /'.trim((string) $this->laravel['config']->get('panneau.path', 'panneau'), '/').'/api.',
        ]);

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
