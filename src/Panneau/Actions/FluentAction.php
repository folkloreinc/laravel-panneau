<?php

namespace Panneau\Actions;

use Illuminate\Support\Str;
use Panneau\Actions\Concerns\SerializesDeclaration;
use Panneau\Contracts\ActionDeclaration;

/**
 * An action declared with a builder, with no PHP logic.
 *
 * @phpstan-consistent-constructor
 */
abstract class FluentAction implements ActionDeclaration
{
    use SerializesDeclaration;

    protected ?string $label = null;

    protected ?string $icon = null;

    /**
     * @var list<ActionScope>
     */
    protected array $scope = [ActionScope::Item];

    /**
     * @var list<ActionPlacement>|null
     */
    protected ?array $placement = null;

    protected bool $destructive = false;

    protected Confirm|bool|null $confirm = null;

    final public function __construct(protected string $id) {}

    public static function make(string $id): static
    {
        return new static($id);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  list<ActionScope>  $scope
     */
    public function scope(array $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * @param  list<ActionPlacement>  $placement
     */
    public function placement(array $placement): static
    {
        $this->placement = $placement;

        return $this;
    }

    public function destructive(bool $destructive = true): static
    {
        $this->destructive = $destructive;

        return $this;
    }

    public function confirm(Confirm|bool $confirm = true): static
    {
        $this->confirm = $confirm;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseDefinition(string $kind): array
    {
        return $this->declaration(
            $kind,
            $this->label ?? Str::ucfirst(str_replace('-', ' ', $this->id)),
            $this->icon,
            $this->scope,
            $this->placement,
            $this->destructive,
            $this->confirm,
        );
    }
}
