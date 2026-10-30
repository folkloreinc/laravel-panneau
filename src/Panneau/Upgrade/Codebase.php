<?php

namespace Panneau\Upgrade;

use Illuminate\Filesystem\Filesystem;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * The PHP code of a project, read without loading or running it: the upgrade reads projects
 * whose classes extend 1.x classes that no longer exist.
 *
 * @internal
 */
final class Codebase
{
    private readonly Parser $parser;

    /**
     * @var array<string, list<Stmt>|null>
     */
    private array $parsed = [];

    /**
     * @var array<string, PhpClass>|null
     */
    private ?array $classes = null;

    /**
     * @var list<string>
     */
    private array $errors = [];

    public function __construct(public readonly string $basePath, private readonly Filesystem $files)
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function path(string $relative): string
    {
        return rtrim($this->basePath, '/').'/'.ltrim($relative, '/');
    }

    public function exists(string $relative): bool
    {
        return $this->files->exists($this->path($relative));
    }

    public function read(string $relative): ?string
    {
        return $this->files->isFile($this->path($relative)) ? (string) $this->files->get($this->path($relative)) : null;
    }

    /**
     * The statements of a PHP file, with names resolved to fully qualified names. Null when the
     * file doesn't exist or can't be parsed.
     *
     * @return list<Stmt>|null
     */
    public function parse(string $relative): ?array
    {
        if (array_key_exists($relative, $this->parsed)) {
            return $this->parsed[$relative];
        }
        $code = $this->read($relative);
        if ($code === null) {
            return $this->parsed[$relative] = null;
        }
        try {
            $stmts = $this->parser->parse($code) ?? [];
        } catch (Error $error) {
            $this->errors[] = sprintf('%s: %s', $relative, $error->getMessage());

            return $this->parsed[$relative] = null;
        }
        $traverser = new NodeTraverser(new NameResolver);

        /** @var list<Stmt> $stmts */
        $stmts = $traverser->traverse($stmts);

        return $this->parsed[$relative] = $stmts;
    }

    /**
     * Nodes of a class in a PHP file.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function find(string $relative, string $class): array
    {
        return array_values((new NodeFinder)->findInstanceOf($this->parse($relative) ?? [], $class));
    }

    /**
     * The classes of the project, in its PSR-4 directories, by name.
     *
     * @return array<string, PhpClass>
     */
    public function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }
        $this->classes = [];
        foreach ($this->phpFiles($this->sourceDirectories()) as $relative) {
            foreach ($this->find($relative, Stmt\Class_::class) as $node) {
                if ($node->namespacedName === null) {
                    continue;
                }
                $name = $node->namespacedName->toString();
                $this->classes[$name] = new PhpClass(
                    $name,
                    $node->extends?->toString(),
                    array_map(fn (Node\Name $interface): string => $interface->toString(), $node->implements),
                    $relative,
                    $node,
                );
            }
        }
        ksort($this->classes);

        return $this->classes;
    }

    public function class(string $name): ?PhpClass
    {
        return $this->classes()[ltrim($name, '\\')] ?? null;
    }

    /**
     * The parents of a class, nearest first, up to the first class that isn't the project's.
     *
     * @return list<string>
     */
    public function ancestors(string $name): array
    {
        $ancestors = [];
        $class = $this->class($name);
        while ($class?->parent !== null && ! in_array($class->parent, $ancestors, true)) {
            $ancestors[] = $class->parent;
            $class = $this->class($class->parent);
        }

        return $ancestors;
    }

    /**
     * The first class outside the project that a class extends.
     */
    public function externalBase(string $name): ?string
    {
        $ancestors = $this->ancestors($name);

        return $ancestors === [] ? null : $ancestors[count($ancestors) - 1];
    }

    /**
     * Whether a class extends one of the given classes, or implements one of the interfaces.
     *
     * @param  list<string>  $classes
     */
    public function isA(string $name, array $classes): bool
    {
        foreach ([$name, ...$this->ancestors($name)] as $class) {
            if (in_array($class, $classes, true)) {
                return true;
            }
            foreach ($this->class($class)->interfaces ?? [] as $interface) {
                if (in_array($interface, $classes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The project's classes that extend one of the given classes.
     *
     * @param  list<string>  $classes
     * @return array<string, PhpClass>
     */
    public function subclassesOf(array $classes): array
    {
        return array_filter(
            $this->classes(),
            fn (PhpClass $class): bool => $this->isA($class->name, $classes) && ! in_array($class->name, $classes, true),
        );
    }

    /**
     * PHP files under directories, relative to the base path.
     *
     * @param  list<string>  $directories
     * @return list<string>
     */
    public function phpFiles(array $directories, string $pattern = '*.php'): array
    {
        $directories = array_values(array_filter(
            array_map(fn (string $directory): string => $this->path($directory), $directories),
            fn (string $directory): bool => $this->files->isDirectory($directory),
        ));
        if ($directories === []) {
            return [];
        }
        $files = [];
        foreach (Finder::create()->files()->name($pattern)->in($directories)->sortByName() as $file) {
            $files[] = ltrim(substr($file->getPathname(), strlen(rtrim($this->basePath, '/'))), '/');
        }

        return array_values(array_unique($files));
    }

    /**
     * The PSR-4 directories of composer.json, or `app`.
     *
     * @return list<string>
     */
    public function sourceDirectories(): array
    {
        $composer = json_decode($this->read('composer.json') ?? '', true);
        $directories = [];
        foreach ((array) ($composer['autoload']['psr-4'] ?? []) as $paths) {
            foreach ((array) $paths as $path) {
                if (is_string($path) && ! in_array(trim($path, '/'), ['database/factories', 'database/seeders'], true)) {
                    $directories[] = trim($path, '/');
                }
            }
        }

        return $directories === [] ? ['app'] : array_values(array_unique($directories));
    }

    /**
     * The namespace of the `app` directory in composer.json, with its trailing backslash.
     */
    public function appNamespace(): string
    {
        $composer = json_decode($this->read('composer.json') ?? '', true);
        foreach ((array) ($composer['autoload']['psr-4'] ?? []) as $namespace => $paths) {
            if (is_string($namespace) && in_array('app', array_map(fn ($path): string => trim((string) $path, '/'), (array) $paths), true)) {
                return $namespace;
            }
        }

        return 'App\\';
    }

    /**
     * Files that couldn't be parsed.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
