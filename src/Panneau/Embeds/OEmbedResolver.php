<?php

namespace Panneau\Embeds;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Str;
use Panneau\Contracts\EmbedResolver;

/**
 * Resolves URLs through the oEmbed endpoints of known providers (`panneau.embeds.providers`).
 *
 * Only a URL that matches the schemes of a provider is resolved, and only that provider's
 * endpoint is requested: the URL itself is never fetched, so it can't reach the app's network.
 * Results are cached `panneau.embeds.cache_ttl` seconds.
 */
final class OEmbedResolver implements EmbedResolver
{
    private const TYPES = ['video', 'audio', 'photo', 'rich', 'link'];

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    public function resolve(string $url): ?array
    {
        $provider = $this->provider($url);
        if ($provider === null) {
            return null;
        }
        $key = 'panneau:embeds:'.hash('sha256', $url);
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        try {
            $response = $this->http
                ->timeout((int) $this->config->get('panneau.embeds.timeout', 5))
                ->acceptJson()
                ->get($provider['endpoint'], ['url' => $url, 'format' => 'json']);
        } catch (ConnectionException) {
            return null;
        }
        $data = $response->successful() ? $response->json() : null;
        if (! is_array($data)) {
            return null;
        }

        $value = self::toValue($url, $data, $provider);
        $ttl = $this->config->get('panneau.embeds.cache_ttl', 86400);
        if (is_numeric($ttl) && (int) $ttl > 0) {
            $this->cache->put($key, $value, (int) $ttl);
        }

        return $value;
    }

    /**
     * The provider whose schemes match the URL.
     *
     * @return array{name: string, endpoint: string, type: string|null}|null
     */
    private function provider(string $url): ?array
    {
        $providers = $this->config->get('panneau.embeds.providers', []);
        foreach (is_array($providers) ? $providers : [] as $provider) {
            if (! is_array($provider) || ! is_string($provider['endpoint'] ?? null)) {
                continue;
            }
            $schemes = is_array($provider['schemes'] ?? null) ? array_filter($provider['schemes'], 'is_string') : [];
            if (Str::is($schemes, $url, ignoreCase: true)) {
                return [
                    'name' => is_string($provider['name'] ?? null) ? $provider['name'] : '',
                    'endpoint' => $provider['endpoint'],
                    'type' => is_string($provider['type'] ?? null) ? $provider['type'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * The protocol's `EmbedValue` from an oEmbed response.
     *
     * @param  array<array-key, mixed>  $data
     * @param  array{name: string, endpoint: string, type: string|null}  $provider
     * @return array<string, mixed>
     */
    private static function toValue(string $url, array $data, array $provider): array
    {
        $string = fn (string $key): ?string => is_string($data[$key] ?? null) && $data[$key] !== '' ? $data[$key] : null;
        $number = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;
        $type = $provider['type'] ?? $string('type');

        return array_filter([
            'url' => $url,
            'type' => in_array($type, self::TYPES, true) ? $type : 'link',
            'title' => $string('title'),
            'provider' => $string('provider_name') ?? ($provider['name'] !== '' ? $provider['name'] : null),
            // A photo's own URL is its image
            'thumbnailUrl' => $string('thumbnail_url') ?? ($type === 'photo' ? $string('url') : null),
            'html' => $string('html'),
            'width' => $number('width'),
            'height' => $number('height'),
        ], fn (mixed $value): bool => $value !== null);
    }
}
