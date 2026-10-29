<?php

namespace Panneau\Rector;

use Illuminate\Filesystem\Filesystem;
use Panneau\Upgrade\Codebase;

/**
 * The parents of the project's classes, read from its files: Rector can't load classes that
 * extend 1.x classes, since 5.x doesn't have them. The project is the directory Rector runs in.
 *
 * @internal
 */
final class ProjectClasses
{
    /**
     * @var array<string, Codebase>
     */
    private array $code = [];

    public function __construct(private readonly ?string $basePath = null) {}

    /**
     * Whether a class is one of the given classes, or extends one of them through the
     * project's classes.
     *
     * @param  list<string>  $classes
     */
    public function isA(string $class, array $classes): bool
    {
        return in_array(ltrim($class, '\\'), $classes, true) || $this->code()->isA(ltrim($class, '\\'), $classes);
    }

    /**
     * The nearest of the given classes that a class is or extends.
     *
     * @param  list<string>  $classes
     */
    public function nearest(string $class, array $classes): ?string
    {
        foreach ([ltrim($class, '\\'), ...$this->code()->ancestors(ltrim($class, '\\'))] as $ancestor) {
            if (in_array($ancestor, $classes, true)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Whether a class of the project, or one of its parents in the project, declares a method.
     */
    public function declares(string $class, string $method): bool
    {
        foreach ([ltrim($class, '\\'), ...$this->code()->ancestors(ltrim($class, '\\'))] as $ancestor) {
            if ($this->code()->class($ancestor)?->method($method) !== null) {
                return true;
            }
        }

        return false;
    }

    private function code(): Codebase
    {
        $path = $this->basePath ?? (string) getcwd();

        return $this->code[$path] ??= new Codebase($path, new Filesystem);
    }
}
