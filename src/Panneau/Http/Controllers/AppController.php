<?php

namespace Panneau\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as View;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Panneau\Auth\EmailVerification;
use Panneau\Auth\TwoFactor;
use Panneau\Contracts\Panneau;
use Panneau\Definition\DefinitionBuilder;

/**
 * The page of the app: the boot config and the project's Vite entry, which mounts
 * `@panneau/app`. Served for every path of the app, since the app routes on the client. Guests
 * get it in the `panneau` auth mode, without the definition: the app shows its login page. So do
 * users whose address isn't verified, with `panneau.auth.email_verification`: the app shows its
 * verification page, and those who must set up two-factor authentication: the app shows its
 * setup.
 *
 * The view gets the CSP nonce of Vite (`Vite::useCspNonce()`), if the request has one, which the
 * default view writes in `<meta property="csp-nonce">` for `@panneau/app`.
 *
 * @internal
 */
final class AppController
{
    public function __construct(
        private readonly Panneau $panneau,
        private readonly DefinitionBuilder $definition,
        private readonly Config $config,
        private readonly View $view,
        private readonly Vite $vite,
        private readonly EmailVerification $verification,
        private readonly TwoFactor $twoFactor,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        // With a context, the definition is built for the values in the prefix of the path
        $boot = $this->panneau->bootConfig(
            $user,
            $user !== null && ! $this->verification->mustVerify($user) && ! $this->twoFactor->mustSetUp($user) && (bool) $this->config->get('panneau.app.inline_definition', true),
            $this->appPath($request),
        );
        $entry = $this->config->get('panneau.app.entry');
        $buildDirectory = $this->config->get('panneau.app.build_directory');
        $view = $this->config->get('panneau.app.view');

        $html = $this->view->make(is_string($view) ? $view : 'panneau::app', [
            'boot' => $boot,
            'title' => $this->definition->name(),
            'entry' => is_string($entry) || is_array($entry) ? $entry : 'resources/js/panneau.tsx',
            'buildDirectory' => is_string($buildDirectory) ? $buildDirectory : null,
            'nonce' => $this->vite->cspNonce(),
        ])->render();

        return new Response($html, 200, ['Cache-Control' => 'no-store, private']);
    }

    /**
     * The path of the app after `panneau.path` (and the prefix of the caller's group), as the
     * request has it, URL-encoded: the route parameter is decoded, and a value of the context
     * can have a `/`.
     */
    private function appPath(Request $request): string
    {
        $uri = $request->route()?->uri() ?? '';
        $prefix = count(explode('/', trim($uri, '/'))) - 1;

        return implode('/', array_slice(explode('/', trim($request->path(), '/')), max(0, $prefix)));
    }
}
