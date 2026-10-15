<?php

namespace Panneau\Actions;

use LogicException;

/**
 * An action that navigates: to a URL template (`/pages/{id}/preview`), or to a named route of
 * the definition.
 */
final class LinkAction extends FluentAction
{
    private ?string $url = null;

    private ?string $route = null;

    /**
     * A URL template, with `{id}` for the item.
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * The name of a route in the definition.
     */
    public function route(string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function toDefinition(): array
    {
        if ($this->url === null && $this->route === null) {
            throw new LogicException(sprintf('The link action "%s" needs a url() or a route().', $this->id));
        }

        return array_filter(
            [...$this->baseDefinition('link'), 'url' => $this->url, 'route' => $this->route],
            fn (mixed $value): bool => $value !== null,
        );
    }
}
