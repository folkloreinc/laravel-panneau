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
        ?string $tone = null,
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
            'tone' => $tone,
            'confirm' => $confirm instanceof Confirm ? $confirm->toDefinition() : $confirm,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * A tone's name, as the protocol writes them: lowercase letters, digits and dashes.
     */
    protected static function checkedTone(?string $tone): ?string
    {
        if ($tone !== null && preg_match('/^[a-z0-9-]+$/', $tone) !== 1) {
            throw new \InvalidArgumentException(sprintf('The tone "%s" of an action is not a tone name, such as "info" or "brand".', $tone));
        }

        return $tone;
    }
}
