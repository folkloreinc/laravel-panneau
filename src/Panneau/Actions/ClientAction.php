<?php

namespace Panneau\Actions;

use LogicException;

/**
 * An action handled in the browser by a plugin, such as a custom modal. It is authorized as a
 * server action is, by the policy: `runAction()` for the resource, and on each item the method
 * named after the action (`copyLink`), `runAction()`, or `update`.
 */
final class ClientAction extends FluentAction
{
    private ?string $component = null;

    /**
     * The plugin handler.
     */
    public function component(string $component): self
    {
        $this->component = $component;

        return $this;
    }

    /**
     * Whether the action runs on items: its scope has `item` or `selection`. Each item then says
     * in its abilities whether the user can run it, as for a server action.
     */
    public function appliesToItems(): bool
    {
        return in_array(ActionScope::Item, $this->scope, true)
            || in_array(ActionScope::Selection, $this->scope, true);
    }

    public function toDefinition(): array
    {
        if ($this->component === null) {
            throw new LogicException(sprintf('The client action "%s" needs a component().', $this->id));
        }

        return [...$this->baseDefinition('client'), 'component' => $this->component];
    }
}
