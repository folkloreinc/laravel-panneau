<?php

namespace Panneau\Auth;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The URLs of the auth pages of the app, which the links of the emails open (panneau-js decision
 * 0048): `{path}/auth/reset-password` and `{path}/auth/verify-email`, outside the context.
 *
 * @internal
 */
final class AuthPages
{
    public const RESET_PASSWORD = 'auth/reset-password';

    public const VERIFY_EMAIL = 'auth/verify-email';

    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $url,
    ) {}

    /**
     * @param  array<string, string>  $query
     */
    public function url(string $page, array $query): string
    {
        $path = trim((string) $this->config->get('panneau.path', 'panneau'), '/');

        return $this->url->to(($path !== '' ? $path.'/' : '').$page).'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
