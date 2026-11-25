<?php

namespace Panneau\Agents;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Panneau\Context\Context;
use Panneau\Resources\MediaResource;

/**
 * The upload of a file by an agent (decision 0032): a temporary signed URL of Panneau that the
 * `upload_file` tool answers, and that receives the file with one `PUT`, without the token of
 * the connection. The URL is a signed route of Laravel, which bounds its life; what it was made
 * for (the user, the connection, the library, the name of the file, the other values of the
 * media and the context) is its `upload` parameter, encrypted by Laravel's encrypter with the
 * key of the app, so that the URL shows none of it and nothing of it can be changed. Nothing is
 * stored.
 *
 * @internal
 */
final class FileUpload
{
    /**
     * The name of the route that receives the file.
     */
    public const ROUTE = 'panneau.mcp.upload';

    /**
     * The longest URL the tool answers: over it, proxies and servers refuse the request.
     */
    public const MAX_URL_LENGTH = 4000;

    public function __construct(
        private readonly StringEncrypter $encrypter,
        private readonly UrlGenerator $url,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * Seconds the URL of an upload lives: `panneau.agents.uploads.url_ttl`.
     */
    public function ttl(): int
    {
        $ttl = $this->config->get('panneau.agents.uploads.url_ttl');

        return is_numeric($ttl) ? max(1, (int) $ttl) : 900;
    }

    /**
     * The largest file the route reads, in bytes: `panneau.agents.uploads.max_size`. Null: no
     * limit but those of the file field and of the web server.
     */
    public function maxSize(): ?int
    {
        $size = $this->config->get('panneau.agents.uploads.max_size');

        return is_numeric($size) ? max(0, (int) $size) : null;
    }

    /**
     * The absolute URL that takes the file of a new media of a library, for a user, in the
     * current context. Its signature covers its path and query, not its host: the URL holds
     * behind a proxy that changes the scheme or the host.
     *
     * @param  array<string, mixed>  $values  The other values of the media.
     */
    public function url(Authenticatable $user, ?AgentCaller $agent, MediaResource $resource, string $name, array $values): string
    {
        $identifier = $user->getAuthIdentifier();
        $claims = $this->encrypter->encryptString((string) json_encode([
            'user' => is_int($identifier) || is_string($identifier) ? (string) $identifier : null,
            'agent' => $agent !== null ? ['id' => $agent->id, 'name' => $agent->name] : null,
            'context' => $this->container->make(Context::class)->all(),
            'resource' => $resource->id(),
            'name' => $name,
            'values' => $values,
        ]));

        return $this->url->to($this->url->temporarySignedRoute(
            self::ROUTE,
            Carbon::now()->addSeconds($this->ttl()),
            ['upload' => $claims],
            absolute: false,
        ));
    }

    /**
     * What the URL of a request was made for, or null when its `upload` isn't one this app
     * encrypted. The signature and the expiry of the URL are checked by the route.
     *
     * @return array{user: string, agent: AgentCaller|null, context: array<string, string>, resource: string, name: string, values: array<string, mixed>}|null
     */
    public function claims(Request $request): ?array
    {
        $upload = $request->query('upload');
        if (! is_string($upload) || $upload === '') {
            return null;
        }
        try {
            $claims = json_decode($this->encrypter->decryptString($upload), true);
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($claims)
            || ! is_string($claims['user'] ?? null)
            || ! is_string($claims['resource'] ?? null)
            || ! is_string($claims['name'] ?? null)
            || ! is_array($claims['context'] ?? null)
            || ! is_array($claims['values'] ?? null)) {
            return null;
        }
        $agent = $claims['agent'] ?? null;

        return [
            'user' => $claims['user'],
            'agent' => is_array($agent) && is_string($agent['id'] ?? null)
                ? new AgentCaller($agent['id'], is_string($agent['name'] ?? null) ? $agent['name'] : null)
                : null,
            'context' => array_filter($claims['context'], is_string(...)),
            'resource' => $claims['resource'],
            'name' => $claims['name'],
            'values' => $claims['values'],
        ];
    }
}
