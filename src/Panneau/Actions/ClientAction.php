<?php

namespace Panneau\Actions;

use LogicException;

/**
 * An action handled in the browser by a plugin, such as a custom modal.
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

    public function toDefinition(): array
    {
        if ($this->component === null) {
            throw new LogicException(sprintf('The client action "%s" needs a component().', $this->id));
        }

        return [...$this->baseDefinition('client'), 'component' => $this->component];
    }
}
