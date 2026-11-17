<?php

namespace Panneau\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * Redirections of the routes that the app reaches with a full navigation, such as the login with
 * a provider or a switch of language: back to `next`, a local path under the path of the app, or
 * to the app. The class has the config as `$this->config`.
 *
 * @internal
 */
trait RedirectsToTheApp
{
    /**
     * A redirection without the page that Symfony writes in its body: a browser follows it.
     */
    private function redirectTo(string $url): RedirectResponse
    {
        $response = new RedirectResponse($url);
        $response->setContent('');

        return $response;
    }

    private function isLocal(string $path): bool
    {
        $base = $this->appPath();

        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            && ($path === $base || str_starts_with($path, rtrim($base, '/').'/') || str_starts_with($path, rtrim($base, '/').'?'));
    }

    private function appPath(): string
    {
        return '/'.trim((string) $this->config->get('panneau.path', 'panneau'), '/');
    }
}
