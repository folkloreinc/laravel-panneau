<?php

namespace Panneau\Contracts;

use Closure;

interface Panneau
{
    public function name();

    public function locales();

    public function locale();

    public function guard();

    public function guardName();

    public function routes(Closure $callback = null);

    public function document($name);

    public function hasDocument($name);

    public function documents();

    public function block($name);

    public function hasBlock($name);

    public function blocks();

    public function resource($name);

    public function hasResource($name);

    public function resources();

    public function field($name);

    public function hasField($name);

    public function fields();

    public function layout($name = null);

    public function definition();
}
