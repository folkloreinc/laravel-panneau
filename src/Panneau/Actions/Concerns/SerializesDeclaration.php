<?php

namespace Panneau\Actions\Concerns;

use Panneau\Actions\ActionPlacement;
use Panneau\Actions\ActionScope;
use Panneau\Actions\Confirm;

/**
 * @internal
 */
trait SerializesDeclaration
{
    /**
     * @param  list<ActionScope>  $scope
     * @param  list<ActionPlacement>|null  $placement
     * @return array<string, mixed>
     */
    protected function declaration(
        string $kind,
        string $label,
        ?string $icon,
        array $scope,
        ?array $placement,
        bool $destructive,
        Confirm|bool|null $confirm,
    ): array {
        return array_filter([
            'id' => $this->id(),
            'label' => $label,
            'icon' => $icon,
            'kind' => $kind,
            'scope' => array_map(fn (ActionScope $scope): string => $scope->value, $scope),
            'placement' => $placement !== null
                ? array_map(fn (ActionPlacement $placement): string => $placement->value, $placement)
                : null,
            'destructive' => $destructive ?: null,
            'confirm' => $confirm instanceof Confirm ? $confirm->toDefinition() : $confirm,
        ], fn (mixed $value): bool => $value !== null);
    }
}
