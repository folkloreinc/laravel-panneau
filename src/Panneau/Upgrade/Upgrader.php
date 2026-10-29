<?php

namespace Panneau\Upgrade;

use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Panneau\Console\JsInstaller;
use PhpParser\Node\Expr\Array_;

/**
 * `panneau:install --upgrade`: prints the audit of a 1.x project (`--check`), or writes its 5.x
 * files. A file it replaces is kept next to it as `{file}.1x.bak`; it never replaces a file it
 * doesn't recognize, or a backup, without `--force`.
 *
 * @internal
 */
final class Upgrader
{
    public const BACKUP = '.1x.bak';

    private readonly Factory $components;

    private readonly Codebase $code;

    private readonly Audit $audit;

    public function __construct(
        private readonly Command $command,
        private readonly Filesystem $files,
        private readonly string $basePath,
        private readonly bool $force,
        private readonly bool $interactive = false,
    ) {
        $this->components = new Factory($command->getOutput());
        $this->code = new Codebase($basePath, $files);
        $this->audit = new Audit($this->code);
    }

    public function audit(): Audit
    {
        return $this->audit;
    }

    /**
     * Prints what the project has, and what each part becomes in 5.x. Writes nothing.
     */
    public function check(): int
    {
        if (! $this->isProject1x()) {
            $this->components->info('No 1.x Panneau found: config/panneau.php and routes/panneau.php are not 1.x, and no class extends Panneau\\Support\\Resource.');

            return Command::SUCCESS;
        }
        foreach ($this->audit->sections() as $section) {
            $this->printSection($section);
        }
        $this->printErrors();
        $this->command->newLine();
        $this->components->info(sprintf(
            '%d to move by hand (manual). php artisan panneau:install --upgrade writes the 5.x config, service provider, routes, page and JS entry.',
            $this->audit->manualCount(),
        ));

        return Command::SUCCESS;
    }

    /**
     * Writes the 5.x files of the project.
     */
    public function upgrade(bool $withJs): int
    {
        if (! $this->isProject1x()) {
            $this->components->error('No 1.x Panneau found: run php artisan panneau:install instead.');

            return Command::FAILURE;
        }
        $namespace = $this->code->appNamespace();

        $this->writeConfig();
        $provider = 'app/Providers/PanneauServiceProvider.php';
        $this->write($provider, $this->stub('provider', [
            'namespace' => $namespace,
            'resources' => implode('', array_map(fn (string $resource): string => '            \\'.$resource."::class,\n", $this->audit->resources())),
        ]), $this->isOldProvider($namespace.'Providers\\PanneauServiceProvider'));
        $this->write(Audit::ROUTES, $this->stub('routes'), $this->audit->routesShape() === '1.x');
        $view = Audit::VIEWS.'/app.blade.php';
        if ($this->code->exists($view)) {
            $this->write($view, (string) $this->files->get(__DIR__.'/../../resources/views/app.blade.php'), ! str_contains((string) $this->code->read($view), 'panneau-boot'));
        }
        $this->registerProvider($namespace.'Providers\\PanneauServiceProvider');
        if ($withJs) {
            $this->writeJs();
        }

        $this->printManual();
        $this->deleteGeneratedFiles();
        $this->printErrors();

        $this->command->newLine();
        $this->components->info('Next steps:');
        $this->components->bulletList(array_values(array_filter([
            'Run the Rector rules on the resources and fields (docs/migration.md).',
            "Port the 1.x PanneauPolicy to the viewPanneau gate of {$provider}: nobody can use the admin until then.",
            'Write a policy for every item class: resources without one are denied.',
            $this->audit->usesPanneauLogin() ? "Set 'auth.login_url' in config/panneau.php to a login page of the site: 1.x served its own." : null,
            $withJs ? 'Run npm install, then npm run dev:panneau (or vite), and open the admin.' : null,
            'php artisan panneau:install --upgrade --check lists what is left.',
        ])));

        return Command::SUCCESS;
    }

    private function isProject1x(): bool
    {
        return $this->audit->configShape() === '1.x'
            || $this->audit->routesShape() === '1.x'
            || $this->code->subclassesOf([V1::RESOURCE]) !== [];
    }

    private function writeConfig(): void
    {
        $stub = (string) $this->files->get(__DIR__.'/../../config/panneau.php');
        $config = $this->audit->config();
        if ($this->audit->configShape() === '5.x') {
            $this->components->twoColumnDetail(Audit::CONFIG, '<fg=yellow>KEPT (already 5.x)</>');

            return;
        }
        if ($config === null) {
            $this->write(Audit::CONFIG, (new ConfigConverter($stub, new Array_, $this->audit->guard()))->convert(), false);

            return;
        }
        $converted = (new ConfigConverter($stub, $config, $this->audit->guard(), $this->audit->middleware()))->convert();
        $this->write(Audit::CONFIG, $converted, true);
    }

    private function isOldProvider(string $class): bool
    {
        foreach ($this->audit->providers() as $provider) {
            if ($provider->name === $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registers the 5.x provider where the 1.x provider was, even commented out, or else in
     * bootstrap/providers.php.
     */
    private function registerProvider(string $class): void
    {
        $registered = false;
        foreach (['bootstrap/providers.php', 'config/app.php'] as $file) {
            $code = $this->code->read($file);
            if ($code === null) {
                continue;
            }
            if (preg_match('/^\s*\\\\?'.preg_quote($class, '/').'::class/m', $code) === 1) {
                $registered = true;

                continue;
            }
            $replaced = $code;
            foreach ($this->audit->providers() as $provider) {
                $replaced = (string) preg_replace(
                    '/^(\s*)(?:\/\/\s*)?\\\\?'.preg_quote($provider->name, '/').'::class,?.*$/m',
                    '$1\\'.$class.'::class,',
                    $replaced,
                    1,
                );
            }
            if ($replaced !== $code) {
                $this->write($file, $replaced, true);
                $registered = true;
            }
        }
        if ($registered) {
            return;
        }
        if ($this->code->exists('bootstrap/providers.php')
            && ServiceProvider::addProviderToBootstrapFile($class, $this->code->path('bootstrap/providers.php'))) {
            $this->components->twoColumnDetail('bootstrap/providers.php', '<fg=green>ADDED '.$class.'</>');

            return;
        }
        $this->components->warn("Register {$class} in the providers of config/app.php.");
    }

    private function writeJs(): void
    {
        $js = new JsInstaller($this->files, $this->components, $this->basePath);
        $this->write(JsInstaller::ENTRY, $this->stub('entry'), false);
        if ($js->viteConfig() !== null) {
            $js->updatePackageJson();
            $js->updateViteConfig();

            return;
        }
        // The site keeps its bundler: Vite builds the admin next to it
        $js->writeViteConfig();
        $js->updatePackageJson(
            array_merge_recursive(JsInstaller::PACKAGES, JsInstaller::VITE_PACKAGES),
            ['build:panneau' => 'vite build', 'dev:panneau' => 'vite'],
            'npm run build:panneau',
        );
    }

    /**
     * Writes a file. An existing file is replaced when it is a 1.x file (`$replaceable`) or with
     * `--force`, and kept as `{file}.1x.bak`. An older backup that differs is never replaced
     * without `--force`.
     */
    private function write(string $relative, string $contents, bool $replaceable): void
    {
        $path = $this->code->path($relative);
        if (! $this->files->exists($path)) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $contents);
            $this->components->twoColumnDetail($relative, '<fg=green>WRITTEN</>');

            return;
        }
        $current = (string) $this->files->get($path);
        if ($current === $contents) {
            $this->components->twoColumnDetail($relative, '<fg=yellow>KEPT</>');

            return;
        }
        if (! $replaceable && ! $this->force) {
            $this->components->twoColumnDetail($relative, '<fg=yellow>KEPT (not a 1.x file: use --force to replace it)</>');

            return;
        }
        $backup = $path.self::BACKUP;
        if ($this->files->exists($backup) && (string) $this->files->get($backup) !== $current && ! $this->force) {
            $this->components->twoColumnDetail($relative, '<fg=red>REFUSED ('.$relative.self::BACKUP.' exists: use --force to replace both)</>');

            return;
        }
        $this->files->put($backup, $current);
        $this->files->put($path, $contents);
        $this->components->twoColumnDetail($relative, '<fg=green>REPLACED (1.x kept in '.$relative.self::BACKUP.')</>');
    }

    private function printSection(Section $section): void
    {
        $this->command->newLine();
        $this->command->line('  <options=bold>'.$section->title.'</>');
        foreach ($section->findings as $finding) {
            $this->printFinding($finding, 0);
        }
    }

    private function printFinding(Finding $finding, int $depth): void
    {
        $location = $finding->location !== null && $depth === 0 ? ' <fg=gray>'.$finding->location.'</>' : '';
        $target = $finding->isManual() ? '<fg=yellow>'.$finding->target.'</>' : $finding->target;
        $indent = $depth === 0 ? '' : str_repeat('· ', $depth - 1).'↳ ';
        $this->components->twoColumnDetail($indent.$finding->subject.$location, $target);
        foreach ($finding->details as $detail) {
            $this->printFinding($detail, $depth + 1);
        }
    }

    /**
     * What the upgrade can't do: the manual findings.
     */
    private function printManual(): void
    {
        $count = $this->audit->manualCount();
        if ($count === 0) {
            return;
        }
        $this->command->newLine();
        $this->components->warn(sprintf('%d to move by hand:', $count));
        foreach ($this->audit->sections() as $section) {
            $lines = [];
            foreach ($section->findings as $finding) {
                foreach ([$finding, ...$finding->details] as $item) {
                    if ($item->isManual()) {
                        $prefix = $item === $finding ? '' : $finding->subject.': ';
                        $location = $item->location ?? $finding->location;
                        $lines[] = [$prefix.$item->subject.($location !== null ? ' <fg=gray>'.$location.'</>' : ''), $item->target];
                    }
                }
            }
            if ($lines === []) {
                continue;
            }
            $this->command->line('  <options=bold>'.$section->title.'</>');
            foreach ($lines as [$subject, $target]) {
                $this->components->twoColumnDetail($subject, '<fg=yellow>'.$target.'</>');
            }
        }
    }

    /**
     * Lists the files that the package now provides, and deletes them once confirmed.
     */
    private function deleteGeneratedFiles(): void
    {
        $files = array_values(array_filter($this->audit->generatedFiles(), fn (string $file): bool => $this->code->exists($file)));
        if ($files === []) {
            return;
        }
        $this->command->newLine();
        $this->components->info('The package now provides these files. Move what they add to the project, then delete them:');
        $this->components->bulletList($files);
        if ($this->interactive && $this->command->confirm('Delete them now?', false)) {
            foreach ($files as $file) {
                $path = $this->code->path($file);
                $this->files->isDirectory($path) ? $this->files->deleteDirectory($path) : $this->files->delete($path);
                $this->components->twoColumnDetail($file, '<fg=red>DELETED</>');
            }
        }
    }

    private function printErrors(): void
    {
        foreach ($this->code->errors() as $error) {
            $this->components->warn('Could not read '.$error);
        }
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
}
