<?php

namespace Panneau\Context;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Panneau\Resources\Resource;

/**
 * A param of the current context, in the definition's `context.params` (panneau-js decision
 * 0045): what the user picks in the app, such as a client or a contest, sent with every request
 * in the `Panneau-Context` header. Its choices are inline `options()`, the items of a
 * `resource()`, or a `search()` and a `find()`.
 *
 * A param depends on the params before it: its choices, its check and its default get the
 * `Context` of their values.
 */
final class ContextParam
{
    /**
     * What a name looks like, as the protocol's schema says.
     */
    public const NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * The first segments of the client routes, which a name can't be: the values of the
     * context are a prefix of the routes (`{basePath}/{name}/{value}/…`).
     */
    public const RESERVED_NAMES = ['resources', 'pages', 'media', 'search', 'account', 'auth'];

    private ?string $label = null;

    private bool $required = false;

    private ?string $allLabel = null;

    /**
     * @var array<array-key, mixed>|Closure|null
     */
    private array|Closure|null $options = null;

    /**
     * @var class-string<\Panneau\Resources\Resource>|string|null
     */
    private ?string $resource = null;

    private ?Closure $search = null;

    private ?Closure $find = null;

    private ?Closure $default = null;

    private ?Closure $fixed = null;

    private function __construct(private readonly string $name)
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || in_array($name, self::RESERVED_NAMES, true)) {
            throw new InvalidArgumentException(sprintf(
                'The context param %s needs a name such as "contest": an identifier, and not "%s".',
                json_encode($name),
                implode('", "', self::RESERVED_NAMES),
            ));
        }
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The label of the param, in the picker. Defaults to its name, as a title.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The app needs a value: without one, it shows a page to pick it. Resources still get no
     * value until the user picks one, unless the param has a `default()`.
     */
    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    /**
     * The label of no value, for an optional param, such as "All clients".
     */
    public function allLabel(string $label): self
    {
        $this->allLabel = $label;

        return $this;
    }

    /**
     * Inline choices, sent in the definition: labels keyed by value, or a list of
     * `['value' => …, 'label' => …, 'description' => …]` (or `ContextOption`), or a closure
     * that returns them, called through the container with `$user` and the `Context` of the
     * params before as `$context`.
     *
     * @param  array<array-key, mixed>|Closure  $options
     */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * The items of a registered resource, by class or id: searched through its repository,
     * within its `scope()` (which reads the params before), and those its policy lets the user
     * `view`. Their label is the item's title (`itemTitle()`), and `Context::item()` gives the
     * item.
     *
     * @param  class-string<\Panneau\Resources\Resource>|string  $resource
     */
    public function resource(string $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * The choices that match a search, for anything that isn't a resource: a closure called
     * through the container with `$q` (maybe empty), `$user` and the `Context` of the params
     * before as `$context`, which returns choices as `options()` does. The endpoint keeps the
     * first 20. Goes with `find()`.
     */
    public function search(Closure $search): self
    {
        $this->search = $search;

        return $this;
    }

    /**
     * The choice of a value, to check it: a closure called through the container with `$value`,
     * `$user` and the `Context` of the params before as `$context`, which returns its label (a
     * string, a `['label' => …]` or a `ContextOption`), or null when the user can't use it.
     * Goes with `search()`.
     */
    public function find(Closure $find): self
    {
        $this->find = $find;

        return $this;
    }

    /**
     * The value that the request fixes, such as the client of its domain: a closure called
     * through the container with `$request`, `$user` and the `Context` of the params before as
     * `$context`, which returns a value, or null when the request fixes none (the user then picks
     * it, as any other param). A fixed value is checked like any other; the definition marks the
     * param `fixed`, the app shows it without a field, and another value in the request is
     * refused with the `403` context problem (panneau-js decision 0045).
     */
    public function fixed(Closure $value): self
    {
        $this->fixed = $value;

        return $this;
    }

    /**
     * The value when the request has none, such as the user's only contest, or the last one:
     * a closure called through the container with `$user` and the `Context` of the params
     * before as `$context`, which returns a value or null. It is checked like any other value;
     * a value the user can't use is left out.
     */
    public function default(Closure $default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @internal
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @internal
     *
     * @return array<array-key, mixed>|Closure|null
     */
    public function inlineOptions(): array|Closure|null
    {
        return $this->options;
    }

    /**
     * @internal
     */
    public function resourceReference(): ?string
    {
        return $this->resource;
    }

    /**
     * @internal
     */
    public function searchCallback(): ?Closure
    {
        return $this->search;
    }

    /**
     * @internal
     */
    public function findCallback(): ?Closure
    {
        return $this->find;
    }

    /**
     * @internal
     */
    public function defaultCallback(): ?Closure
    {
        return $this->default;
    }

    /**
     * @internal
     */
    public function fixedCallback(): ?Closure
    {
        return $this->fixed;
    }

    /**
     * Checks that the param has one source of choices.
     *
     * @internal
     */
    public function check(): void
    {
        $sources = array_filter([
            'options()' => $this->options !== null,
            'resource()' => $this->resource !== null,
            'search()' => $this->search !== null || $this->find !== null,
        ]);
        if (count($sources) !== 1) {
            throw new LogicException(sprintf(
                'The context param "%s" needs one source of choices: options(), resource(), or search() and find()%s.',
                $this->name,
                count($sources) > 1 ? sprintf(', not %s', implode(' and ', array_keys($sources))) : '',
            ));
        }
        if (($this->search === null) !== ($this->find === null)) {
            throw new LogicException(sprintf('The context param "%s" needs both search() and find().', $this->name));
        }
    }

    /**
     * The param in the definition, without its choices and values.
     *
     * @internal
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label ?? Str::headline($this->name),
            // Left out when false, as the protocol's default
            'required' => $this->required ? true : null,
            'allLabel' => $this->allLabel,
        ], fn (mixed $value): bool => $value !== null);
    }
}
