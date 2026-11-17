<?php

namespace Panneau\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Panneau\Definition\InterfaceLocales;
use Panneau\Http\Controllers\Concerns\ReadsRouteParameters;
use Panneau\Http\Controllers\Concerns\RedirectsToTheApp;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `GET /locale/{locale}`: switches the interface to a language of `panneau.interface_locales`
 * (panneau-js decision 0082), for the session, then goes to `next`, a local path under the path
 * of the app, or to the app.
 *
 * @internal
 */
final class LocaleController
{
    use ReadsRouteParameters;
    use RedirectsToTheApp;

    public function __construct(
        private readonly Config $config,
        private readonly InterfaceLocales $locales,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $locale = $this->routeParameter($request, 'locale');
        if (! $this->locales->has($locale)) {
            throw new NotFoundHttpException;
        }
        if ($request->hasSession()) {
            $request->session()->put(InterfaceLocales::SESSION_KEY, $locale);
        }
        $next = $request->query('next');

        return $this->redirectTo(is_string($next) && $this->isLocal($next) ? $next : $this->appPath());
    }
}
