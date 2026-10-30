<?php

namespace Panneau\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Panneau\Contracts\Panneau;
use Panneau\Definition\DefinitionBuilder;

/**
 * The page of the app: the boot config and the project's Vite entry, which mounts
 * `@panneau/app`. Served for every path of the app, since the app routes on the client. Guests
 * get it in the `panneau` auth mode, without the definition: the app shows its login page.
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
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $boot = $this->panneau->bootConfig($user, $user !== null && (bool) $this->config->get('panneau.app.inline_definition', true));
        $entry = $this->config->get('panneau.app.entry');
        $buildDirectory = $this->config->get('panneau.app.build_directory');
        $view = $this->config->get('panneau.app.view');

        $html = $this->view->make(is_string($view) ? $view : 'panneau::app', [
            'boot' => $boot,
            'title' => $this->definition->name(),
            'entry' => is_string($entry) || is_array($entry) ? $entry : 'resources/js/panneau.tsx',
            'buildDirectory' => is_string($buildDirectory) ? $buildDirectory : null,
        ])->render();

        return new Response($html, 200, ['Cache-Control' => 'no-store, private']);
    }
}
