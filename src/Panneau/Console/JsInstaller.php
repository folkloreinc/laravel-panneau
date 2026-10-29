<?php

namespace Panneau\Console;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Filesystem\Filesystem;
use stdClass;

/**
 * Adds the npm packages of the app to package.json, and its entry to the Vite config.
 *
 * @internal
 */
final class JsInstaller
{
    /**
     * The JS entry, which mounts @panneau/app in the page of `panneau.app.view`.
     */
    public const ENTRY = 'resources/js/panneau.tsx';

    /**
     * npm packages, by section of package.json. The peer dependencies of @panneau/app are listed
     * so the project controls their versions. Until 5.0.0, v5 is published on the "alpha" tag
     * while "latest" is v4, so the range is explicit.
     */
    public const PACKAGES = [
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

    /**
     * Vite itself, for a project that builds its site with another bundler.
     */
    public const VITE_PACKAGES = [
        'devDependencies' => [
            'laravel-vite-plugin' => '^3.0.0',
            'vite' => '^8.0.0',
        ],
    ];

    public const VITE_CONFIGS = ['vite.config.js', 'vite.config.ts', 'vite.config.mjs', 'vite.config.mts'];

    public function __construct(
        private readonly Filesystem $files,
        private readonly Factory $components,
        private readonly string $basePath,
    ) {}

    /**
     * The project's Vite config, if it has one.
     */
    public function viteConfig(): ?string
    {
        foreach (self::VITE_CONFIGS as $file) {
            if ($this->files->exists($this->path($file))) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Adds the npm packages that package.json doesn't have. An older major of @panneau/app is
     * replaced; the project's versions of the other packages are kept. Scripts are added when
     * package.json doesn't have them, and `$appendToBuild` is run at the end of its `build`.
     *
     * @param  array<string, array<string, string>>  $packages
     * @param  array<string, string>  $scripts
     */
    public function updatePackageJson(array $packages = self::PACKAGES, array $scripts = [], ?string $appendToBuild = null): void
    {
        $path = $this->path('package.json');
        $package = $this->files->exists($path) ? json_decode((string) $this->files->get($path)) : null;
        if (! $package instanceof stdClass) {
            $this->components->warn('No valid package.json: add '.implode(', ', $this->packageList($packages)).'.');

            return;
        }

        $added = [];
        foreach ($packages as $section => $names) {
            foreach ($names as $name => $version) {
                [$currentSection, $current] = $this->package($package, $name) ?? [$section, null];
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

        $current = (array) ($package->scripts ?? []);
        foreach ($scripts as $name => $command) {
            if (! isset($current[$name])) {
                $current[$name] = $command;
                $added[] = 'the '.$name.' script';
            }
        }
        $build = $current['build'] ?? null;
        if ($appendToBuild !== null && is_string($build) && ! str_contains($build, $appendToBuild)) {
            $current['build'] = $build.' && '.$appendToBuild;
            $added[] = $appendToBuild.' to the build script';
        }
        if ($current !== []) {
            $package->scripts = (object) $current;
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
     * Adds the JS entry to the inputs of laravel-vite-plugin, and the React plugin.
     */
    public function updateViteConfig(): void
    {
        $file = $this->viteConfig();
        $manual = sprintf("add '%s' to the input of laravel() and the react() plugin of @vitejs/plugin-react.", self::ENTRY);
        if ($file === null) {
            $this->components->warn('No Vite config: '.$manual);

            return;
        }

        $config = new ViteConfig((string) $this->files->get($this->path($file)));
        $input = $config->addInput(self::ENTRY);
        $plugin = $config->addPlugin('@vitejs/plugin-react', 'react', 'react()');
        if ($input === null || $plugin === null) {
            $this->components->warn(sprintf('%s could not be updated: %s', $file, $manual));

            return;
        }
        if ($input === false && $plugin === false) {
            $this->components->twoColumnDetail($file, '<fg=yellow>KEPT</>');

            return;
        }
        $this->files->put($this->path($file), $config->source());
        $this->components->twoColumnDetail($file, '<fg=green>UPDATED</>');
    }

    /**
     * Writes a Vite config that only builds the admin, for a project that builds its site with
     * another bundler. It is an ES module: `.js` when package.json says so, `.mjs` otherwise.
     *
     * @return string The file written.
     */
    public function writeViteConfig(): string
    {
        $package = json_decode((string) ($this->files->exists($this->path('package.json')) ? $this->files->get($this->path('package.json')) : ''), true);
        $file = is_array($package) && ($package['type'] ?? null) === 'module' ? 'vite.config.js' : 'vite.config.mjs';
        $this->files->put($this->path($file), (string) $this->files->get(__DIR__.'/../../stubs/vite.stub'));
        $this->components->twoColumnDetail($file, '<fg=green>WRITTEN</>');

        return $file;
    }

    /**
     * The section and the version of an npm package in package.json.
     *
     * @return array{string, string}|null
     */
    private function package(stdClass $package, string $name): ?array
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
     * @param  array<string, array<string, string>>  $packages
     * @return list<string>
     */
    private function packageList(array $packages): array
    {
        $list = [];
        foreach ($packages as $names) {
            foreach ($names as $name => $version) {
                $list[] = $name.'@'.$version;
            }
        }

        return $list;
    }

    private function path(string $relative): string
    {
        return rtrim($this->basePath, '/').'/'.$relative;
    }
}
