<?php

namespace Panneau\Fields;

use Closure;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Panneau\Contracts\EmbedResolver;
use Panneau\Forms\FormMode;
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
 *
 * `providers()` limits the providers: the server then resolves the URL when it validates it,
 * and refuses a URL that it can't resolve, or whose provider isn't listed.
 */
class Embed extends Field
{
    public function type(): string
    {
        return 'embed';
    }

    /**
     * The providers an embed may come from, by the `provider_name` of their oEmbed
     * (`['YouTube', 'Vimeo']`), compared without case: the portable `providers` rule.
     *
     * @param  array<array-key, mixed>  $names
     *
     * @throws InvalidArgumentException Without a name, or with a name that isn't a string.
     */
    public function providers(array $names, ?string $message = null, ?FormMode $mode = null): static
    {
        $checked = [];
        foreach ($names as $name) {
            if (! is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException(sprintf('%s is not the name of a provider.', var_export($name, true)));
            }
            $checked[] = $name;
        }
        if (count($checked) === 0) {
            throw new InvalidArgumentException('providers() needs at least one provider.');
        }

        return $this->rule('providers', $checked, $message, mode: $mode);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array'];
    }

    protected function toLaravelRule(array $rule, string $prefix): ?array
    {
        if ($rule['rule'] !== 'providers') {
            return parent::toLaravelRule($rule, $prefix);
        }
        $names = is_array($rule['value'] ?? null) ? array_values(array_filter($rule['value'], is_string(...))) : [];
        $message = isset($rule['message']) && is_string($rule['message']) ? $rule['message'] : null;

        return count($names) > 0 ? ['providers', self::providersRule($names, $message)] : null;
    }

    /**
     * Resolves the URL of a value, as saving does, and checks its provider.
     *
     * @param  list<string>  $names
     * @return Closure(string, mixed, Closure): void
     */
    private static function providersRule(array $names, ?string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($names, $message): void {
            $url = is_array($value) ? ($value['url'] ?? null) : null;
            // A value without a valid URL fails the rules of its URL
            if (! is_string($url) || preg_match('#^https?://#i', $url) !== 1) {
                return;
            }
            $embed = Container::getInstance()->make(EmbedResolver::class)->resolve($url);
            if ($embed === null) {
                $fail($message ?? __("The :attribute can't be embedded."));

                return;
            }
            $provider = $embed['provider'] ?? null;
            $allowed = array_map(mb_strtolower(...), $names);
            if (! is_string($provider) || ! in_array(mb_strtolower($provider), $allowed, true)) {
                $fail($message ?? __('The :attribute must come from :providers.', ['providers' => self::orList($names)]));
            }
        };
    }

    /**
     * `YouTube, Vimeo or Spotify`.
     *
     * @param  list<string>  $names
     */
    private static function orList(array $names): string
    {
        $last = array_pop($names);

        return count($names) > 0 ? implode(', ', $names).' or '.$last : (string) $last;
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
