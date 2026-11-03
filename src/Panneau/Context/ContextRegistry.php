<?php

namespace Panneau\Context;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * The params of the current context, declared with `Panneau::context()`: a list of
 * `ContextParam`, or a closure that returns them, called through the container with the user as
 * `$user` each time they are read.
 */
final class ContextRegistry
{
    /**
     * @var list<ContextParam>|Closure|null
     */
    private array|Closure|null $params = null;

    public function __construct(private readonly Container $container) {}

    /**
     * Declares the params, in order: each one depends on those before it. Replaces the params
     * declared before.
     *
     * @param  list<ContextParam>|Closure  $params
     */
    public function declare(array|Closure $params): void
    {
        $this->params = $params;
    }

    /**
     * Whether a context is declared. Without one, requests have no context, and the definition
     * none.
     */
    public function declared(): bool
    {
        return $this->params !== null;
    }

    /**
     * The params for a user, checked, in order.
     *
     * @return list<ContextParam>
     */
    public function params(?Authenticatable $user = null): array
    {
        if ($this->params === null) {
            return [];
        }
        $params = $this->params instanceof Closure
            ? $this->container->call($this->params, ['user' => $user])
            : $this->params;
        if (! is_array($params)) {
            throw new LogicException(sprintf('The declaration of the context gives %s instead of a list of ContextParam.', get_debug_type($params)));
        }
        $names = [];
        foreach ($params as $param) {
            if (! $param instanceof ContextParam) {
                throw new LogicException(sprintf('The context has a param that is a %s: params are ContextParam builders.', get_debug_type($param)));
            }
            if (isset($names[$param->name()])) {
                throw new LogicException(sprintf('The context has two params named "%s".', $param->name()));
            }
            $param->check();
            $names[$param->name()] = true;
        }

        return array_values($params);
    }
}
