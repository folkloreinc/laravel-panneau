<?php

namespace Panneau\Context;

use Panneau\Exceptions\ContextRefusedException;

/**
 * The current context of a request (panneau-js decision 0045): the values of the params that
 * `Panneau::context()` declares, sent by the app in the `Panneau-Context` header (or read from
 * the path of the app's page), each one checked for the user. Bound once per request: inject
 * it, or read it in a resource's `scope()` with `$this->context()`.
 *
 * Empty when no context is declared, outside the routes of Panneau, and for the params without
 * a value.
 */
final class Context
{
    /**
     * @param  array<string, ContextOption>  $values  The choice of each param with a value, in
     *                                                the order of the params.
     */
    public function __construct(private array $values = []) {}

    /**
     * The params whose value the request fixes.
     *
     * @var array<string, true>
     */
    private array $fixed = [];

    /**
     * The value of a param, or null without one.
     */
    public function get(string $name): ?string
    {
        return ($this->values[$name] ?? null)?->value;
    }

    /**
     * The value of a param, or the `403` context problem without one: for a resource that
     * needs a value of a required param.
     */
    public function getOrFail(string $name): string
    {
        return $this->get($name) ?? throw new ContextRefusedException($name);
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]);
    }

    /**
     * The values, by name, in the order of the params.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return array_map(fn (ContextOption $option): string => $option->value, $this->values);
    }

    /**
     * The item of the value of a param of a resource (`ContextParam::resource()`), as its
     * repository found it.
     */
    public function item(string $name): ?object
    {
        return ($this->values[$name] ?? null)?->item;
    }

    /**
     * The label of the value of a param.
     */
    public function label(string $name): ?string
    {
        return ($this->values[$name] ?? null)?->label;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * The choice of the value of a param.
     *
     * @internal
     */
    public function option(string $name): ?ContextOption
    {
        return $this->values[$name] ?? null;
    }

    /**
     * A context with these values only.
     *
     * @internal
     *
     * @param  list<string>  $names
     */
    public function only(array $names): self
    {
        $context = new self(array_intersect_key($this->values, array_flip($names)));
        $context->fixed = array_intersect_key($this->fixed, array_flip($names));

        return $context;
    }

    /**
     * Sets the value of a param, after those set before.
     *
     * @internal Filled by Panneau, once the value is checked.
     */
    public function set(string $name, ContextOption $option): void
    {
        $this->values[$name] = $option;
    }

    /**
     * Takes the values of another context.
     *
     * @internal Filled by Panneau, once the values are checked.
     */
    public function replace(self $context): void
    {
        $this->values = $context->values;
        $this->fixed = $context->fixed;
    }

    /**
     * Whether the request fixes the value of a param, such as the client of its domain
     * (`ContextParam::fixed()`): the user can't change it.
     */
    public function isFixed(string $name): bool
    {
        return isset($this->fixed[$name]);
    }

    /**
     * @internal
     */
    public function setFixed(string $name, ContextOption $option): void
    {
        $this->values[$name] = $option;
        $this->fixed[$name] = true;
    }
}
