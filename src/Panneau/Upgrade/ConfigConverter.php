<?php

namespace Panneau\Upgrade;

use LogicException;
use PhpParser\Node\Expr;

/**
 * Turns a 1.x `config/panneau.php` into the 5.x config: the package's config with the 1.x
 * values that have a 5.x key. Keys that aren't 1.x keys are kept at the end. Values are written
 * as the code they were (`env()` calls stay calls).
 *
 * @internal
 */
final class ConfigConverter
{
    /**
     * @param  string  $stub  The package's config.
     * @param  list<string>  $middleware  The project's own middleware, from the 1.x routes and config (Audit::middleware()).
     */
    public function __construct(
        private readonly string $stub,
        private readonly Expr\Array_ $config,
        private readonly ?string $guard,
        private readonly array $middleware = [],
    ) {}

    public function convert(): string
    {
        $config = $this->stub;
        $name = Php::get($this->config, 'name');
        if ($name !== null) {
            $config = $this->replace($config, "'name' => null,", "'name' => ".Php::print($name).',');
        }
        $locales = Php::get($this->config, 'intl.locales') ?? Php::get($this->config, 'locales');
        if ($locales !== null) {
            $config = $this->replace($config, "'locales' => null,", "'locales' => ".Php::print($locales).',');
        }
        $prefix = Php::get($this->config, 'routes.prefix');
        if ($prefix !== null) {
            $value = Php::value($prefix);
            $code = is_string($value) ? var_export(trim($value, '/'), true) : Php::print($prefix);
            $config = $this->replace($config, "'path' => 'panneau',", "'path' => {$code},");
        }
        $domain = Php::get($this->config, 'routes.domain');
        if ($domain !== null) {
            $config = $this->replace($config, "'domain' => null,", "'domain' => ".Php::print($domain).',');
        }
        $middleware = $this->middleware();
        if ($middleware !== []) {
            $config = $this->replace($config, "'middleware' => ['web', 'panneau.auth'],", "'middleware' => ['web', 'panneau.auth', ".implode(', ', $middleware).'],');
        }
        if ($this->guard !== null) {
            $config = $this->replace($config, "'guard' => null,", "'guard' => ".var_export($this->guard, true).',');
        }

        $kept = [];
        foreach (Php::items($this->config) as $key => $value) {
            if (is_string($key) && ! in_array($key, ['name', 'locales', 'resources', 'intl', 'routes', 'only_declared_fields'], true)) {
                $kept[] = '    '.var_export($key, true).' => '.str_replace("\n", "\n    ", Php::print($value)).',';
            }
        }
        if ($kept !== []) {
            $position = strrpos($config, '];');
            if ($position === false) {
                throw new LogicException('The config of the package doesn\'t end with an array.');
            }
            $config = substr($config, 0, $position)
                ."\n    // Kept from the 1.x config: these aren't 5.x keys.\n".implode("\n", $kept)."\n".substr($config, $position);
        }

        return $config;
    }

    /**
     * The middleware of the 1.x routes and config that 5.x doesn't replace (not the session, the
     * auth or the gate), as PHP code.
     *
     * @return list<string>
     */
    private function middleware(): array
    {
        $codes = [];
        foreach ($this->middleware as $middleware) {
            $code = str_contains($middleware, '\\') ? '\\'.ltrim($middleware, '\\').'::class' : var_export($middleware, true);
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function replace(string $config, string $search, string $replace): string
    {
        if (substr_count($config, $search) !== 1) {
            throw new LogicException(sprintf('The config of the package has no single "%s".', $search));
        }

        return str_replace($search, $replace, $config);
    }
}
