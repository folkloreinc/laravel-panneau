<?php

namespace Panneau\Contracts;

/**
 * Gets what a URL embeds, for `embed` fields and `GET /embeds`. The default,
 * `Panneau\Embeds\OEmbedResolver`, asks the oEmbed endpoints of the providers in
 * `panneau.embeds.providers`. Bind another implementation to use another service.
 */
interface EmbedResolver
{
    /**
     * The protocol's `EmbedValue` for a URL (`url`, `type`, `title`, `provider`,
     * `thumbnailUrl`, `html`, `width`, `height`), or null when the URL can't be embedded.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $url): ?array;
}
