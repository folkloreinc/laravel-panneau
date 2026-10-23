<?php

namespace Panneau\Fields;

use Illuminate\Container\Container;
use Panneau\Contracts\EmbedResolver;
use Panneau\Forms\RuleSet;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;

/**
 * The URL of a video, a sound or a post, with what its provider's oEmbed gives: the protocol's
 * `EmbedValue`. The field gets it from `GET /embeds` as the URL is typed.
 *
 * Only the URL is taken from the request. Saving resolves it again through the
 * `EmbedResolver` (from its cache, usually), so the title, the thumbnail and the HTML always
 * come from the provider. A URL that doesn't change keeps the value the item has, and a URL
 * that can't be resolved is saved alone, `{ "url" }`.
 */
class Embed extends Field
{
    public function type(): string
    {
        return 'embed';
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array'];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if (is_array($value)) {
            $rules->add($path.'.url', ['required', 'string', 'url:http,https', 'max:2048']);
        }
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        return is_array($value) ? ['url' => $value['url']] : $value;
    }

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        if (! is_array($value) || ! is_string($value['url'] ?? null)) {
            return $value;
        }
        $url = $value['url'];
        $previous = $context->item !== null ? data_get($context->item, $path) : null;
        if (is_array($previous) && ($previous['url'] ?? null) === $url) {
            return $previous;
        }

        return Container::getInstance()->make(EmbedResolver::class)->resolve($url) ?? ['url' => $url];
    }
}
